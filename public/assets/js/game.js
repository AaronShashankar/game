// MazeQuest game client.
//
// The browser draws the maze, handles movement and shows questions. Everything
// that affects coins/XP/power-ups is a server call (/api/runs/*):
//   start    -> run id, maze seed, gate count, deadline (normal, daily or challenge)
//   question -> one question for a gate (no answer included)
//   answer   -> server checks it, pays the reward, and reveals the right answer
//   powerup  -> server consumes it from the inventory
//   pickup   -> server records a dead-end coin (paid when the maze is finished)
//   complete -> server verifies every gate was cleared, pays, and returns a recap
//   timeout  -> server issues a fresh deadline when the timer runs out
'use strict';

const CFG = window.GameConfig;
const canvas = document.getElementById('mazeCanvas');
const ctx = canvas.getContext('2d');
const popup = document.getElementById('questionPopup');
const qText = document.getElementById('questionText');
const qMeta = document.getElementById('questionMeta');
const optionsDiv = document.getElementById('options');
const levelPopup = document.getElementById('levelCompletePopup');
const shopPopup = document.getElementById('shopPopup');
const $ = (id) => document.getElementById(id);

const SIZE = 600;               // logical canvas size; drawn at device pixel ratio
const POWERS = ['timeFreeze', 'hint', 'skip', 'scoreDoubler'];
const POWER_NAMES = { timeFreeze: 'Time freeze', hint: 'Hint', skip: 'Skip gate', scoreDoubler: 'Score doubler' };
const MIXED = 'Mixed Mode';

// ---- State --------------------------------------------------------------------
let wallet = null;             // coins, xp, level, inventory, equipped… from the server
let run = null;                // current run state from the server
let mode = 'normal';           // how the *next* start request is made: normal | daily | challenge
let challengeCode = null;
let selectedSubject = '';
let selectedDifficulty = 'Easy';
let rows = 10, cols = 10, cellSize = SIZE / cols;
let maze = [], gates = [], pickups = [], player = { x: 0, y: 0 };
let start = { x: 0, y: 0 }, end = { x: 9, y: 9 };
let countdown = null;          // seconds left (display); server is authoritative
let isTimeFrozen = false;
let levelComplete = false;
let busy = false;              // a request that should block input is in flight
let wrongInARow = 0;           // 3 wrong answers in a row sends you back to the start
let hintGate = null;
let drawLoopStarted = false;
let runStartedAt = 0;
let currentAnswers = [];       // answer buttons of the open question, for 1–4 keys
let revealed = false;          // the open question has been answered and shows feedback
let shareCode = null;
let stars = [];                // space theme backdrop

const diffCfg = () => CFG.difficulties[selectedDifficulty] || CFG.difficulties.Easy;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const fmtTime = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
const subjectLabel = (s) => (s === MIXED ? 'Mixed mode' : s);

// ---- Canvas setup & colours -------------------------------------------------------
function setupCanvas() {
  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  canvas.width = SIZE * dpr;
  canvas.height = SIZE * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}
setupCanvas();

// Board palettes. "classic" follows the site's light/dark tokens; bought
// themes have their own fixed palette.
const THEMES = {
  forest: { bg: '#eef3ea', wall: '#2f4a36', muted: '#a9bca9', soft: '#dfe8db', accent: '#3f7d4e', accentSoft: '#d6e8d6', warning: '#b7791f', coin: '#c08a1e' },
  space:  { bg: '#0f1226', wall: '#c9cbff', muted: '#3a3f6b', soft: '#1a1f3d', accent: '#8b8fff', accentSoft: '#23285a', warning: '#fbbf24', coin: '#fbbf24' },
};
const SKINS = { skin2: '#0ea5e9', skin3: '#ef4444' };
let C = {};
function readColors() {
  const css = getComputedStyle(document.documentElement);
  const v = (name) => css.getPropertyValue(name).trim();
  const classic = {
    bg: v('--surface'), wall: v('--text'), muted: v('--border-strong'), soft: v('--surface-2'),
    accent: v('--accent'), accentSoft: v('--accent-soft'), warning: v('--warning'), coin: v('--warning'),
  };
  C = { ...classic, ...(THEMES[wallet?.equipped?.theme] || {}) };
  C.player = SKINS[wallet?.equipped?.skin] || C.accent;
  canvas.style.background = C.bg;
}
readColors();
window.addEventListener('themechange', readColors);

// ---- Seeded maze generation -----------------------------------------------------
// The server hands out a seed; the same seed always builds the same maze, so
// replays, the daily maze and challenges all share a layout.
function mulberry32(seed) {
  return function () {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function generateMaze(seed) {
  const rand = mulberry32(seed);
  maze = [];
  for (let i = 0; i < rows; i++) {
    maze[i] = [];
    for (let j = 0; j < cols; j++) maze[i][j] = { top: true, right: true, bottom: true, left: true, visited: false };
  }
  const shuffle = (a) => { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(rand() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };
  // Iterative DFS (no recursion depth limit on big mazes).
  const stack = [[0, 0]];
  maze[0][0].visited = true;
  while (stack.length) {
    const [x, y] = stack[stack.length - 1];
    const next = shuffle([['up', x - 1, y], ['down', x + 1, y], ['left', x, y - 1], ['right', x, y + 1]])
      .find(([, nx, ny]) => nx >= 0 && nx < rows && ny >= 0 && ny < cols && !maze[nx][ny].visited);
    if (!next) { stack.pop(); continue; }
    const [dir, nx, ny] = next;
    if (dir === 'up') { maze[x][y].top = false; maze[nx][ny].bottom = false; }
    if (dir === 'down') { maze[x][y].bottom = false; maze[nx][ny].top = false; }
    if (dir === 'left') { maze[x][y].left = false; maze[nx][ny].right = false; }
    if (dir === 'right') { maze[x][y].right = false; maze[nx][ny].left = false; }
    maze[nx][ny].visited = true;
    stack.push([nx, ny]);
  }
  maze[start.x][start.y].left = false;
  maze[end.x][end.y].right = false;

  // Starfield for the space theme (fixed per maze).
  const sr = mulberry32(seed ^ 0x2545f491);
  stars = Array.from({ length: 70 }, () => ({ x: sr() * SIZE, y: sr() * SIZE, r: sr() * 1.1 + 0.3, a: sr() * 0.5 + 0.2 }));
}

// The one path from start to exit (BFS through the perfect maze).
function solutionPath() {
  const key = (x, y) => x * cols + y;
  const prev = new Map([[key(start.x, start.y), null]]);
  const queue = [start];
  while (queue.length) {
    const { x, y } = queue.shift();
    if (x === end.x && y === end.y) break;
    const c = maze[x][y];
    for (const [open, nx, ny] of [[!c.top, x - 1, y], [!c.bottom, x + 1, y], [!c.left, x, y - 1], [!c.right, x, y + 1]]) {
      if (open && nx >= 0 && nx < rows && ny >= 0 && ny < cols && !prev.has(key(nx, ny))) {
        prev.set(key(nx, ny), { x, y });
        queue.push({ x: nx, y: ny });
      }
    }
  }
  const path = [];
  for (let p = { ...end }; p; p = prev.get(key(p.x, p.y))) path.unshift(p);
  return path;
}

// Gates are evenly spaced along the path; gate_no counts from the start (1..N),
// matching the gate numbers the server created for this run.
function placeGates(count, serverGates) {
  const inner = solutionPath().slice(2, -2);
  gates = [];
  for (let k = 1; k <= count; k++) {
    const cell = inner[Math.floor((k * inner.length) / (count + 1))];
    const status = serverGates.find((g) => g.gate_no === k)?.status || 'pending';
    gates.push({ gate_no: k, x: cell.x, y: cell.y, opened: status !== 'pending' });
  }
}

// Pickups go in dead ends (cells with three walls), chosen from the seed so a
// replay or challenge puts them in the same places. Detours off the path are
// the price of collecting them.
function placePickups(seed, count, collected) {
  const deadEnds = [];
  for (let i = 0; i < rows; i++) for (let j = 0; j < cols; j++) {
    const c = maze[i][j];
    const walls = [c.top, c.right, c.bottom, c.left].filter(Boolean).length;
    const isEndpoint = (i === start.x && j === start.y) || (i === end.x && j === end.y);
    if (walls === 3 && !isEndpoint) deadEnds.push({ x: i, y: j });
  }
  const rand = mulberry32(seed ^ 0x5bd1e995);
  for (let i = deadEnds.length - 1; i > 0; i--) { const k = Math.floor(rand() * (i + 1)); [deadEnds[i], deadEnds[k]] = [deadEnds[k], deadEnds[i]]; }
  pickups = deadEnds.slice(0, count).map((cell, i) => ({ no: i + 1, ...cell, taken: collected.includes(i + 1) }));
}

// ---- Drawing ----------------------------------------------------------------------
function roundRect(x, y, w, h, r) {
  ctx.beginPath();
  ctx.roundRect ? ctx.roundRect(x, y, w, h, r) : ctx.rect(x, y, w, h);
}

function drawMaze() {
  const s = cellSize;
  ctx.fillStyle = C.bg;
  ctx.fillRect(0, 0, SIZE, SIZE);
  if (wallet?.equipped?.theme === 'space') {
    for (const st of stars) { ctx.fillStyle = `rgba(255,255,255,${st.a})`; ctx.beginPath(); ctx.arc(st.x, st.y, st.r, 0, Math.PI * 2); ctx.fill(); }
  }

  // Walls
  ctx.strokeStyle = C.wall;
  ctx.lineWidth = Math.max(1.5, Math.min(3, s / 12));
  ctx.lineCap = 'round';
  ctx.beginPath();
  for (let i = 0; i < rows; i++) for (let j = 0; j < cols; j++) {
    const x = j * s, y = i * s, c = maze[i]?.[j];
    if (!c) continue;
    if (c.top) { ctx.moveTo(x, y); ctx.lineTo(x + s, y); }
    if (c.right) { ctx.moveTo(x + s, y); ctx.lineTo(x + s, y + s); }
    if (c.bottom) { ctx.moveTo(x, y + s); ctx.lineTo(x + s, y + s); }
    if (c.left) { ctx.moveTo(x, y); ctx.lineTo(x, y + s); }
  }
  ctx.stroke();

  // Pickups: small coins in dead ends
  for (const p of pickups) {
    if (p.taken) continue;
    const cx = p.y * s + s / 2, cy = p.x * s + s / 2, r = s * 0.17;
    ctx.fillStyle = C.coin;
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = C.bg; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2); ctx.stroke();
  }

  // Gates: numbered, outlined in accent until opened
  const g0 = s * 0.18, gs = s * 0.64;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = `600 ${Math.max(9, s * 0.32)}px Inter, system-ui, sans-serif`;
  for (const g of gates) {
    const gx = g.y * s + g0, gy = g.x * s + g0;
    roundRect(gx, gy, gs, gs, s * 0.16);
    if (g.opened) {
      ctx.fillStyle = C.soft; ctx.fill();
      ctx.strokeStyle = C.muted; ctx.lineWidth = 1; ctx.setLineDash([3, 3]); ctx.stroke(); ctx.setLineDash([]);
    } else {
      ctx.fillStyle = C.accentSoft; ctx.fill();
      ctx.strokeStyle = C.accent; ctx.lineWidth = 2; ctx.stroke();
      ctx.fillStyle = C.accent;
      ctx.fillText(String(g.gate_no), gx + gs / 2, gy + gs / 2 + 1);
    }
  }

  // Start marker
  ctx.fillStyle = C.muted;
  ctx.beginPath(); ctx.arc(start.y * s + s / 2, start.x * s + s / 2, s * 0.12, 0, Math.PI * 2); ctx.fill();

  // Exit: solid ink tile with a gentle pulse
  const pulse = 1 + Math.sin(Date.now() / 400) * 0.04;
  const ew = s * 0.56 * pulse;
  roundRect(end.y * s + (s - ew) / 2, end.x * s + (s - ew) / 2, ew, ew, s * 0.14);
  ctx.fillStyle = C.wall; ctx.fill();

  // Player
  const px = player.y * s + s / 2, py = player.x * s + s / 2;
  ctx.fillStyle = C.player;
  ctx.beginPath(); ctx.arc(px, py, s * 0.27, 0, Math.PI * 2); ctx.fill();
  ctx.strokeStyle = C.bg; ctx.lineWidth = 2; ctx.stroke();

  // Fog of war (Hard)
  const fog = diffCfg().fog;
  if (fog && !levelComplete) {
    const r = fog * s;
    const grad = ctx.createRadialGradient(px, py, r * 0.35, px, py, r);
    grad.addColorStop(0, hexA(C.bg, 0));
    grad.addColorStop(1, hexA(C.bg, 0.97));
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, SIZE, SIZE);
  }
  // Hint highlight sits above the fog
  if (hintGate) {
    ctx.strokeStyle = C.warning; ctx.lineWidth = 3;
    roundRect(hintGate.y * s + g0 - 3, hintGate.x * s + g0 - 3, gs + 6, gs + 6, s * 0.2);
    ctx.stroke();
  }
  requestAnimationFrame(drawMaze);
}

// "#rrggbb" + alpha -> rgba(); other formats are returned unchanged.
function hexA(hex, a) {
  const m = /^#([0-9a-f]{6})$/i.exec(hex);
  if (!m) return hex;
  const n = parseInt(m[1], 16);
  return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${a})`;
}

// ---- HUD / messages ---------------------------------------------------------------
function bump(id) {
  const el = $(id);
  if (!el) return;
  el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
}

function updateHUD() {
  if (!wallet) return;
  const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
  set('coins', wallet.coins.toLocaleString());
  set('shopCoins', wallet.coins.toLocaleString());
  set('xp', `${wallet.xp_into_level}/${wallet.xp_for_next}`);
  set('levelDisplay', wallet.level);
  set('combo', run ? `${run.combo}×` : '0×');
  App.setCoins(wallet.coins);
  for (const p of POWERS) {
    const n = wallet.inventory[p] || 0;
    set(p + 'Count', n);
    const btn = $(p + 'Btn');
    if (btn) btn.disabled = n === 0;
  }
  $('scoreDoublerBtn')?.classList.toggle('power-up-active', !!run?.doubler);
}

function renderTimer() {
  const el = $('timeLeft');
  el.textContent = countdown === null ? 'No limit' : fmtTime(Math.max(0, countdown));
  const box = $('timer');
  box.classList.toggle('low-time', countdown !== null && countdown <= 10 && !isTimeFrozen);
  box.classList.toggle('frozen', isTimeFrozen);
}

function renderProgress() {
  const opened = gates.filter((g) => g.opened).length;
  $('gateProgress').textContent = `${opened} / ${gates.length} gates`;
  $('gateProgress').classList.toggle('chip-success', gates.length > 0 && opened === gates.length);
  const chip = $('pickupProgress');
  chip.hidden = pickups.length === 0;
  chip.lastElementChild.textContent = `${pickups.filter((p) => p.taken).length} / ${pickups.length}`;
}

function renderRunInfo() {
  $('runSubject').textContent = subjectLabel(selectedSubject);
  $('runDifficulty').textContent = `${selectedDifficulty} · ${diffCfg().size} × ${diffCfg().size}${diffCfg().fog ? ' · fog of war' : ''}`;
  const chip = $('modeChip');
  chip.hidden = run.mode === 'normal';
  chip.textContent = run.mode === 'daily' ? 'Daily maze' : 'Challenge';
  const opp = run.opponent;
  $('opponentBox').hidden = !opp;
  if (opp) $('opponentText').textContent = `Beat ${opp.username}'s time of ${fmtTime(opp.duration_seconds)}`;
  // The daily maze is one attempt: no restarts.
  $('restartBtn').hidden = run.mode === 'daily';
}

const toast = (msg, type) => App.toast(msg, type);

function applyServer(result) {
  if (result.wallet) {
    const themeChanged = result.wallet.equipped?.theme !== wallet?.equipped?.theme || result.wallet.equipped?.skin !== wallet?.equipped?.skin;
    wallet = result.wallet;
    if (themeChanged) readColors();
  }
  if (result.run) syncRun(result.run);
  (result.new_achievements || []).forEach((a) => toast(`Achievement unlocked: ${a.name}`, 'success'));
  updateHUD();
}

// Server state wins: timer, gates and pickups follow the latest run state.
let unfreezeTimer = null;
function syncRun(state) {
  run = state;
  // During a Time Freeze the server deadline already includes the frozen
  // seconds; the visible clock shows what's left once the freeze ends.
  if (state.seconds_left !== null) countdown = state.seconds_left - state.freeze_left;
  isTimeFrozen = state.freeze_left > 0;
  clearTimeout(unfreezeTimer);
  if (isTimeFrozen) unfreezeTimer = setTimeout(() => { isTimeFrozen = false; renderTimer(); }, state.freeze_left * 1000);
  for (const g of gates) {
    const s = state.gates.find((sg) => sg.gate_no === g.gate_no);
    if (s) g.opened = s.status !== 'pending';
  }
  for (const p of pickups) if (state.pickups.includes(p.no)) p.taken = true;
  renderTimer();
  renderProgress();
}

function reportError(e) {
  toast(e.message || 'Something went wrong', 'danger');
}

function openModal(el) { el.classList.add('show'); }
function closeModal(el) { el.classList.remove('show'); }

// ---- Run lifecycle ----------------------------------------------------------------
function startBody(replayOf) {
  if (mode === 'daily') return { mode: 'daily' };
  if (mode === 'challenge') return { mode: 'challenge', code: challengeCode };
  return { difficulty: selectedDifficulty, subject: selectedSubject, replay_of: replayOf };
}

async function startRun(replayOf = null) {
  busy = true;
  try {
    const r = await App.api('/api/runs/start', { body: startBody(replayOf) });
    run = r.run;
    selectedDifficulty = r.run.difficulty;
    selectedSubject = r.run.subject;
    const cfg = diffCfg();
    rows = cols = cfg.size;
    cellSize = SIZE / cols;
    start = { x: 0, y: 0 };
    end = { x: rows - 1, y: cols - 1 };
    player = { ...start };
    levelComplete = false;
    wrongInARow = 0;
    hintGate = null;
    shareCode = null;
    generateMaze(r.run.seed);
    placeGates(r.run.gate_count, r.run.gates);
    placePickups(r.run.seed, r.run.pickup_count, r.run.pickups);
    syncRun(r.run);
    renderRunInfo();
    runStartedAt = Date.now();
    updateHUD();
    if (r.resumed) toast("Picking up today's maze where you left it. The clock kept running.");
    if (!drawLoopStarted) { drawLoopStarted = true; drawMaze(); }
    canvas.focus();
  } catch (e) {
    reportError(e);
    if (e.code === 'DAILY_DONE' || e.code === 'OWN_CHALLENGE' || e.code === 'NOT_FOUND') {
      setTimeout(() => (location.href = App.url('dashboard')), 2500);
    }
  } finally {
    busy = false;
  }
}

// After a daily or challenge run, "Next maze" carries on with normal mazes
// of the same difficulty and subject.
function nextLevel() {
  closeModal(levelPopup);
  mode = 'normal';
  history.replaceState(null, '', `${location.pathname}?difficulty=${selectedDifficulty.toLowerCase()}&subject=${encodeURIComponent(selectedSubject)}`);
  startRun();
}

// Same maze layout, fresh run (gates closed again).
function restartLevel() {
  if (run?.mode === 'daily') return;
  closeModal(levelPopup);
  mode = 'normal';
  startRun(run?.run_id ?? null);
}

function goToDashboard() {
  location.href = App.url('dashboard');
}

async function completeLevel() {
  if (levelComplete) return;
  levelComplete = true;
  busy = true;
  try {
    let r;
    for (let tries = 0; ; tries++) {
      try {
        r = await App.api('/api/runs/complete', { body: { run_id: run.run_id } });
        break;
      } catch (e) {
        // A very fast human can beat the server's minimum time; wait it out.
        if (e.code !== 'TOO_FAST' || tries >= 3) throw e;
        toast('Checking your run…');
        await sleep(Math.max(1000, diffCfg().min_seconds * 1000 - (Date.now() - runStartedAt)));
      }
    }
    applyServer(r);
    showSummary(r);
    Sound.play('complete');
    if (r.level_up) toast(`Level up! You're now level ${r.level_up}.`, 'success');
  } catch (e) {
    levelComplete = false;
    reportError(e);
  } finally {
    busy = false;
  }
}

// ---- End-of-run summary -------------------------------------------------------------
function showSummary(r) {
  const s = r.summary;
  shareCode = r.share_code;
  const ch = r.challenge;

  $('levelTitle').textContent = ch ? (ch.won ? `You beat ${ch.opponent}!` : `${ch.opponent} was faster this time`)
    : run.mode === 'daily' ? 'Daily maze complete' : 'Maze complete';
  $('levelSummary').textContent = `${subjectLabel(selectedSubject)} · ${selectedDifficulty}`;
  $('resultIcon').innerHTML = App.icon(ch && !ch.won ? 'flag' : 'check');

  $('sumTime').textContent = fmtTime(s.duration_seconds);
  $('sumAccuracy').textContent = s.accuracy === null ? '—' : `${s.accuracy}%`;
  $('sumCombo').textContent = `${s.best_combo}×`;
  $('sumPickups').textContent = `${s.pickups} / ${s.pickup_count}`;

  const lines = [['Maze reward', `+${r.coins - r.time_bonus} coins · +${r.xp} XP`]];
  if (r.time_bonus) lines.push(['Time bonus', `+${r.time_bonus} coins`]);
  if (r.pickup_coins) lines.push(['Coins found', `+${r.pickup_coins} coins`]);
  if (r.daily_bonus) lines.push(['Daily bonus', `+${r.daily_bonus.coins} coins · +${r.daily_bonus.xp} XP`]);
  $('rewardLines').innerHTML = lines.map(([k, v]) => `<div><span>${App.escape(k)}</span><strong>${App.escape(v)}</strong></div>`).join('');

  const callout = $('resultCallout');
  callout.hidden = true;
  if (ch) {
    callout.hidden = false;
    callout.className = 'callout' + (ch.won ? ' callout-accent' : '');
    callout.innerHTML = `${App.icon('swords')}<div class="grow">Your time <strong>${fmtTime(ch.your_seconds)}</strong> vs ${App.escape(ch.opponent)}'s <strong>${fmtTime(ch.opponent_seconds)}</strong></div>`;
  } else if (r.daily) {
    callout.hidden = false;
    callout.className = 'callout callout-accent';
    callout.innerHTML = `${App.icon('trophy')}<div class="grow">You're <strong>#${r.daily.rank}</strong> of ${r.daily.finishers} on today's maze.</div>
      <a class="btn btn-ghost btn-sm" href="${App.url('leaderboard')}?type=daily">Standings</a>`;
  }

  // Recap: every question missed, with the right answer and why.
  $('recap').hidden = s.missed.length === 0;
  $('recapList').innerHTML = s.missed.map((m) => `
    <div class="recap-item">
      <div class="recap-q">${App.escape(m.prompt)}</div>
      <div class="recap-a">You said ${App.escape(m.your_answer ?? '—')} · <span class="ok">Answer: ${App.escape(m.correct_answer ?? '—')}</span></div>
      ${m.explanation ? `<div class="recap-why">${App.escape(m.explanation)}</div>` : ''}
    </div>`).join('');

  $('replayBtn').hidden = run.mode === 'daily';
  $('shareBtn').hidden = !shareCode;
  openModal(levelPopup);
}

async function shareChallenge() {
  if (!shareCode) return;
  const url = `${location.origin}${App.url('challenge')}?code=${shareCode}`;
  const text = `I finished a ${selectedDifficulty} ${subjectLabel(selectedSubject)} maze on MazeQuest in ${$('sumTime').textContent}. Can you beat me?`;
  try {
    if (navigator.share) {
      await navigator.share({ title: 'MazeQuest challenge', text, url });
      return;
    }
    await navigator.clipboard.writeText(`${text} ${url}`);
    toast('Challenge link copied. Send it to a friend!', 'success');
  } catch (e) {
    if (e.name === 'AbortError') return;   // share sheet dismissed
    window.prompt('Copy this link and send it to a friend:', url);
  }
}

// ---- Questions --------------------------------------------------------------------
async function askGate(gate) {
  busy = true;
  let q;
  try {
    q = await App.api('/api/runs/question', { body: { run_id: run.run_id, gate_no: gate.gate_no } });
  } catch (e) {
    busy = false;
    if (e.code === 'GATE_NOT_PENDING') { gate.opened = true; renderProgress(); return; }
    return reportError(e);
  }
  busy = false;
  Sound.play('gate');
  const shownAt = Date.now();
  revealed = false;

  qMeta.innerHTML =
    `<span class="chip chip-accent">Gate ${gate.gate_no} of ${gates.length}</span>` +
    `<span class="chip">${App.escape(q.subject)}</span>` +
    (q.is_review ? `<span class="chip chip-warning">${App.icon('refresh')}Review</span>` : '') +
    (q.attempt_no > 1 ? `<span class="chip chip-warning">Attempt ${q.attempt_no}</span>` : '');
  qText.textContent = q.prompt;
  optionsDiv.innerHTML = '';
  optionsDiv.classList.remove('is-revealed');
  $('feedback').hidden = true;
  $('continueBtn').hidden = true;
  $('questionHint').hidden = false;

  // Show options in random order but submit the original index.
  const order = q.options.map((text, idx) => ({ text, idx })).sort(() => Math.random() - 0.5);
  currentAnswers = order.map((opt, i) => {
    const btn = document.createElement('button');
    btn.className = 'answer';
    btn.dataset.idx = opt.idx;
    btn.innerHTML = `<span class="kbd">${i + 1}</span><span></span>`;
    btn.lastChild.textContent = opt.text;
    btn.onclick = async () => {
      if (btn.disabled || revealed) return;
      optionsDiv.querySelectorAll('button').forEach((b) => (b.disabled = true));
      btn.classList.add('is-picked');
      // The server ignores answers sent <1s after the question was served.
      const wait = 1100 - (Date.now() - shownAt);
      if (wait > 0) await sleep(wait);
      await submitAnswer(gate, q.attempt_no, opt.idx);
    };
    optionsDiv.appendChild(btn);
    return btn;
  });
  openModal(popup);
}

// Mark the right (and the picked wrong) option and explain.
function revealAnswer(r, picked) {
  revealed = true;
  currentAnswers = [];
  optionsDiv.classList.add('is-revealed');
  optionsDiv.querySelectorAll('.answer').forEach((b) => {
    b.classList.remove('is-picked');
    const idx = Number(b.dataset.idx);
    if (idx === r.correct_idx) b.classList.add('is-correct');
    else if (idx === picked) b.classList.add('is-wrong');
  });
  const fb = $('feedback');
  fb.hidden = false;
  fb.className = 'feedback ' + (r.correct ? 'is-correct' : 'is-wrong');
  $('feedbackTitle').innerHTML = r.correct
    ? `${App.icon('check')}Correct · +${r.coins} coins${r.combo > 1 ? ` · ${r.combo}× combo` : ''}${r.was_review ? ' · review cleared' : ''}`
    : `${App.icon('x')}Not quite${r.coins ? ` · ${r.coins} coins` : ''}`;
  $('feedbackText').textContent = r.explanation || '';
  $('feedbackText').hidden = !r.explanation;
  $('questionHint').hidden = true;
  $('continueBtn').hidden = false;
  $('continueBtn').focus();
}

function closeQuestion() {
  if (busy) return;
  closeModal(popup);
  currentAnswers = [];
  revealed = false;
  canvas.focus();
}

async function submitAnswer(gate, attemptNo, option, retry = 0) {
  busy = true;
  try {
    const r = await App.api('/api/runs/answer', { body: { run_id: run.run_id, gate_no: gate.gate_no, attempt_no: attemptNo, option } });
    applyServer(r);
    revealAnswer(r, option);
    if (r.correct) {
      wrongInARow = 0;
      Sound.play('correct');
      bump('coins'); bump('xp');
      // Keep the pace on a right answer; a wrong one waits so the explanation can be read.
      const id = run.run_id;
      setTimeout(() => { if (revealed && run.run_id === id && popup.classList.contains('show')) closeQuestion(); }, r.explanation ? 1800 : 900);
    } else {
      Sound.play('wrong');
      wrongInARow++;
      if (wrongInARow >= 3) {
        player = { ...start };
        wrongInARow = 0;
        toast('Three wrong answers in a row: back to the start', 'danger');
      }
    }
  } catch (e) {
    // Network blip: resend the same attempt (the server de-duplicates it).
    if (e.code === 'NETWORK' && retry < 2) {
      await sleep(800);
      busy = false;
      return submitAnswer(gate, attemptNo, option, retry + 1);
    }
    reportError(e);
    closeModal(popup);
  } finally {
    busy = false;
  }
}

// ---- Movement ---------------------------------------------------------------------
function inputBlocked() {
  return busy || levelComplete || !maze.length || !run ||
    [popup, levelPopup, shopPopup].some((p) => p.classList.contains('show'));
}

// An unopened gate blocks the cell: walking into it asks its question.
function tryMove(dx, dy) {
  if (inputBlocked()) return;
  const c = maze[player.x][player.y];
  if ((dx === -1 && c.top) || (dx === 1 && c.bottom) || (dy === -1 && c.left) || (dy === 1 && c.right)) return;
  const nx = player.x + dx, ny = player.y + dy;
  if (nx < 0 || nx >= rows || ny < 0 || ny >= cols) return;
  const gate = gates.find((g) => g.x === nx && g.y === ny && !g.opened);
  if (gate) { askGate(gate); return; }
  player = { x: nx, y: ny };
  const p = pickups.find((pk) => pk.x === nx && pk.y === ny && !pk.taken);
  if (p) collectPickup(p);
  if (nx === end.x && ny === end.y) completeLevel();
}

async function collectPickup(p) {
  p.taken = true;
  renderProgress();
  Sound.play('pickup');
  toast(`Coin found · +${CFG.pickupCoins} when you finish`);
  try {
    const r = await App.api('/api/runs/pickup', { body: { run_id: run.run_id, pickup_no: p.no } });
    syncRun(r.run);
  } catch (e) {
    if (e.code !== 'RUN_NOT_ACTIVE') { p.taken = false; renderProgress(); }
  }
}

const KEY_DIRS = {
  ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
  w: [-1, 0], s: [1, 0], a: [0, -1], d: [0, 1], W: [-1, 0], S: [1, 0], A: [0, -1], D: [0, 1],
};
document.addEventListener('keydown', (e) => {
  if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
  const questionOpen = popup.classList.contains('show');
  if (questionOpen && revealed && (e.key === 'Enter' || e.key === ' ' || e.key === 'Escape')) {
    e.preventDefault();
    closeQuestion();
    return;
  }
  if (e.key === 'Escape') {
    closeModal(shopPopup);
    if (!busy && questionOpen && !revealed) closeQuestion();
    return;   // the finish dialog's buttons are the only way on
  }
  // 1–4: answer the open question, otherwise use a power-up.
  if (/^[1-4]$/.test(e.key)) {
    const n = Number(e.key) - 1;
    if (questionOpen) { currentAnswers[n]?.click(); return; }
    if (!inputBlocked()) usePower(POWERS[n]);
    return;
  }
  const dir = KEY_DIRS[e.key];
  if (!dir || !maze.length) return;
  e.preventDefault();
  tryMove(dir[0], dir[1]);
});

// Swipe on the board.
(function enableSwipe() {
  let sx = 0, sy = 0;
  canvas.style.touchAction = 'none';
  canvas.addEventListener('touchstart', (e) => { const t = e.changedTouches[0]; sx = t.clientX; sy = t.clientY; }, { passive: true });
  canvas.addEventListener('touchend', (e) => {
    const t = e.changedTouches[0];
    const dx = t.clientX - sx, dy = t.clientY - sy;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) return;
    if (Math.abs(dx) > Math.abs(dy)) tryMove(0, dx > 0 ? 1 : -1);
    else tryMove(dy > 0 ? 1 : -1, 0);
  }, { passive: true });
})();

// On-screen D-pad (shown on touch screens): tap to step, hold to keep moving.
(function enableDpad() {
  const DIRS = { up: [-1, 0], down: [1, 0], left: [0, -1], right: [0, 1] };
  let repeat = null;
  const stop = (btn) => { clearInterval(repeat); repeat = null; btn?.classList.remove('is-held'); };
  document.querySelectorAll('#dpad button').forEach((btn) => {
    const [dx, dy] = DIRS[btn.dataset.dir];
    btn.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      btn.setPointerCapture?.(e.pointerId);
      btn.classList.add('is-held');
      tryMove(dx, dy);
      clearInterval(repeat);
      repeat = setInterval(() => tryMove(dx, dy), 150);
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((ev) => btn.addEventListener(ev, () => stop(btn)));
    btn.addEventListener('contextmenu', (e) => e.preventDefault());
  });
})();

// ---- Timer --------------------------------------------------------------------------
let timingOut = false;
setInterval(async () => {
  if (countdown === null || isTimeFrozen || levelComplete || !run || timingOut) return;
  countdown--;
  renderTimer();
  if (countdown > 0) return;
  timingOut = true;
  try {
    const r = await App.api('/api/runs/timeout', { body: { run_id: run.run_id } });
    closeModal(popup);
    revealed = false;
    player = { ...start };
    wrongInARow = 0;
    applyServer(r);
    Sound.play('timeout');
    toast("Time's up: back to the start", 'danger');
  } catch (e) {
    if (e.code === 'NOT_EXPIRED') countdown = 2;  // clocks slightly apart: try again shortly
    else reportError(e);
  } finally {
    timingOut = false;
  }
}, 1000);

// ---- Power-ups ----------------------------------------------------------------------
function nearestPendingGate() {
  return gates.filter((g) => !g.opened)
    .sort((a, b) => (Math.abs(player.x - a.x) + Math.abs(player.y - a.y)) - (Math.abs(player.x - b.x) + Math.abs(player.y - b.y)))[0];
}

async function usePower(type) {
  if (!run || levelComplete) return;
  if (!(wallet?.inventory[type] > 0)) {
    toast(`No ${POWER_NAMES[type].toLowerCase()} left`);
    return showShop();
  }
  if (type === 'timeFreeze' && countdown === null) return toast('No timer on Easy, so your time freeze is saved.');
  const target = type === 'skip' || type === 'hint' ? nearestPendingGate() : null;
  if ((type === 'skip' || type === 'hint') && !target) return toast('Every gate is already open.');

  try {
    const r = await App.api('/api/runs/powerup', {
      body: { run_id: run.run_id, type, gate_no: type === 'skip' ? target.gate_no : null },
    });
    applyServer(r);
    Sound.play('power');
    if (type === 'timeFreeze') {
      toast(`Timer frozen for ${r.effect.frozen_seconds} seconds`, 'success');
    } else if (type === 'hint') {
      hintGate = target;
      toast('Nearest gate highlighted');
      setTimeout(() => (hintGate = null), 3000);
    } else if (type === 'skip') {
      toast(`Gate ${target.gate_no} opened`, 'success');
    } else if (type === 'scoreDoubler') {
      toast('Your next correct answer pays double', 'success');
    }
  } catch (e) {
    reportError(e);
  }
}

// ---- Sound toggle -------------------------------------------------------------------
function renderSoundBtn() {
  const btn = $('soundBtn');
  btn.innerHTML = App.icon(Sound.muted ? 'volume-x' : 'volume');
  btn.title = Sound.muted ? 'Sound off' : 'Sound on';
  btn.setAttribute('aria-label', Sound.muted ? 'Turn sound on' : 'Mute sound');
}
function toggleSound() { Sound.toggle(); renderSoundBtn(); canvas.focus(); }
renderSoundBtn();

// ---- In-game shop -------------------------------------------------------------------
const ITEM_ICONS = { timeFreeze: 'snowflake', hint: 'search', skip: 'skip', scoreDoubler: 'trend', pack: 'package', megaPack: 'gem' };

async function showShop() {
  updateHUD();
  openModal(shopPopup);
  const list = $('shopList');
  try {
    const { items, wallet: w } = await App.api('/api/shop');
    wallet = w;
    updateHUD();
    const sellable = items.filter((i) => i.active && (i.kind === 'powerup' || i.kind === 'pack'));
    list.innerHTML = '';
    for (const item of sellable) {
      const row = document.createElement('div');
      row.className = 'list-row';
      row.innerHTML = `
        <div class="icon-box">${App.icon(ITEM_ICONS[item.code] || 'package')}</div>
        <div class="grow">
          <div class="list-title">${App.escape(item.name)}</div>
          <div class="list-meta">${App.escape(item.description)}${item.kind === 'powerup' ? ` · You have ${wallet.inventory[item.code] || 0}` : ''}</div>
        </div>
        <span class="price">${App.icon('coins')}${item.price}</span>
        <button class="btn btn-secondary btn-sm">Buy</button>`;
      const btn = row.querySelector('button');
      btn.disabled = wallet.coins < item.price;
      btn.addEventListener('click', () => buyItem(item.code, item.name));
      list.appendChild(row);
    }
  } catch (e) {
    list.innerHTML = `<div class="empty">${App.escape(e.message)}</div>`;
  }
}
function hideShop() { closeModal(shopPopup); canvas.focus(); }
shopPopup.addEventListener('click', (e) => { if (e.target === shopPopup) hideShop(); });

async function buyItem(code, label) {
  try {
    const r = await App.api('/api/shop/buy', { body: { item: code } });
    applyServer(r);
    bump('coins');
    toast(`Bought ${label}`, 'success');
    showShop();
  } catch (e) {
    reportError(e);
  }
}

// ---- Boot ---------------------------------------------------------------------------
// /game?daily=1, /game?challenge=CODE, or /game?difficulty=…&subject=…
(async function boot() {
  const params = new URLSearchParams(location.search);
  if (params.get('daily') === '1') {
    mode = 'daily';
    selectedDifficulty = CFG.dailyDifficulty;
    selectedSubject = MIXED;
  } else if (params.get('challenge')) {
    mode = 'challenge';
    challengeCode = params.get('challenge');
  } else {
    const d = (params.get('difficulty') || '').toLowerCase();
    if (['easy', 'medium', 'hard'].includes(d)) selectedDifficulty = d.charAt(0).toUpperCase() + d.slice(1);
    const subject = params.get('subject');
    if (!subject || !(CFG.subjects.includes(subject) || subject === MIXED)) {
      location.replace(App.url('choose-subject') + (d ? `?difficulty=${d}` : ''));
      return;
    }
    selectedSubject = subject;
  }
  try {
    wallet = (await App.api('/api/me')).wallet;
    readColors();
    updateHUD();
  } catch (e) {
    return reportError(e);
  }
  renderTimer();
  startRun();
})();
