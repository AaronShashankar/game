// MazeQuest game client.
//
// The browser draws the maze, handles movement and shows questions. Everything
// that affects coins/XP/power-ups is a server call (/api/runs/*):
//   start    -> run id, maze seed, gate count, deadline
//   question -> one question for a gate (no answer included)
//   answer   -> server checks it and pays the reward
//   powerup  -> server consumes it from the inventory
//   complete -> server verifies every gate was cleared and pays the finish reward
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

// ---- State --------------------------------------------------------------------
let wallet = null;             // coins, xp, level, inventory… from the server
let run = null;                // current run state from the server
let selectedSubject = '';
let selectedDifficulty = 'Easy';
let rows = 10, cols = 10, cellSize = SIZE / cols;
let maze = [], gates = [], player = { x: 0, y: 0 };
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

const diffCfg = () => CFG.difficulties[selectedDifficulty] || CFG.difficulties.Easy;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---- Canvas setup & theme colours ------------------------------------------------
function setupCanvas() {
  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  canvas.width = SIZE * dpr;
  canvas.height = SIZE * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}
setupCanvas();

let C = {};
function readColors() {
  const css = getComputedStyle(document.documentElement);
  const v = (name) => css.getPropertyValue(name).trim();
  C = {
    bg: v('--surface'), wall: v('--text'), muted: v('--border-strong'), soft: v('--surface-2'),
    accent: v('--accent'), accentSoft: v('--accent-soft'), text3: v('--text-3'), warning: v('--warning'),
  };
}
readColors();
window.addEventListener('themechange', readColors);

// ---- Seeded maze generation -----------------------------------------------------
// The server hands out a seed; the same seed always builds the same maze, so
// "Restart" can replay a layout and the server could verify paths later.
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

// ---- Drawing ----------------------------------------------------------------------
function roundRect(x, y, w, h, r) {
  ctx.beginPath();
  ctx.roundRect ? ctx.roundRect(x, y, w, h, r) : ctx.rect(x, y, w, h);
}

function drawMaze() {
  const s = cellSize;
  ctx.fillStyle = C.bg;
  ctx.fillRect(0, 0, SIZE, SIZE);

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

  // Player (skin colour from owned cosmetics, otherwise the accent)
  const px = player.y * s + s / 2, py = player.x * s + s / 2;
  const inv = wallet?.inventory || {};
  ctx.fillStyle = inv.skin3 ? '#ef4444' : inv.skin2 ? '#0ea5e9' : C.accent;
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
  set('streak', wallet.streak);
  set('levelDisplay', wallet.level);
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
  if (countdown === null) el.textContent = 'No limit';
  else {
    const t = Math.max(0, countdown);
    el.textContent = `${Math.floor(t / 60)}:${String(t % 60).padStart(2, '0')}`;
  }
  const box = $('timer');
  box.classList.toggle('low-time', countdown !== null && countdown <= 10 && !isTimeFrozen);
  box.classList.toggle('frozen', isTimeFrozen);
}

function renderGateProgress() {
  const opened = gates.filter((g) => g.opened).length;
  $('gateProgress').textContent = `${opened} / ${gates.length} gates`;
  $('gateProgress').classList.toggle('chip-success', gates.length > 0 && opened === gates.length);
}

const toast = (msg, type) => App.toast(msg, type);

function applyServer(result) {
  if (result.wallet) wallet = result.wallet;
  if (result.run) syncRun(result.run);
  (result.new_badges || []).forEach((b) => toast(`Badge unlocked: ${b}`, 'success'));
  updateHUD();
}

// Server state wins: timer and gate status follow the latest run state.
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
  renderTimer();
  renderGateProgress();
}

function reportError(e) {
  toast(e.message || 'Something went wrong', 'danger');
}

function openModal(el) { el.classList.add('show'); }
function closeModal(el) { el.classList.remove('show'); }

// ---- Run lifecycle ----------------------------------------------------------------
async function startRun(replayOf = null) {
  busy = true;
  try {
    const r = await App.api('/api/runs/start', {
      body: { difficulty: selectedDifficulty, subject: selectedSubject, replay_of: replayOf },
    });
    const cfg = diffCfg();
    rows = cols = cfg.size;
    cellSize = SIZE / cols;
    start = { x: 0, y: 0 };
    end = { x: rows - 1, y: cols - 1 };
    player = { ...start };
    levelComplete = false;
    wrongInARow = 0;
    hintGate = null;
    generateMaze(r.run.seed);
    placeGates(r.run.gate_count, r.run.gates);
    syncRun(r.run);
    runStartedAt = Date.now();
    updateHUD();
    if (!drawLoopStarted) { drawLoopStarted = true; drawMaze(); }
    canvas.focus();
  } catch (e) {
    reportError(e);
  } finally {
    busy = false;
  }
}

function nextLevel() {
  closeModal(levelPopup);
  startRun();
}

// Same maze layout, fresh run (gates closed again).
function restartLevel() {
  closeModal(levelPopup);
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
    $('resultCoins').textContent = `+${r.coins}`;
    $('resultXp').textContent = `+${r.xp}`;
    $('levelSummary').textContent = r.time_bonus
      ? `Includes a ${r.time_bonus}-coin time bonus.`
      : `${selectedSubject} · ${selectedDifficulty}`;
    openModal(levelPopup);
    if (r.level_up) toast(`Level up! You're now level ${r.level_up}.`, 'success');
  } catch (e) {
    levelComplete = false;
    reportError(e);
  } finally {
    busy = false;
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
    if (e.code === 'GATE_NOT_PENDING') { gate.opened = true; renderGateProgress(); return; }
    return reportError(e);
  }
  busy = false;
  const shownAt = Date.now();

  qMeta.innerHTML =
    `<span class="chip chip-accent">Gate ${gate.gate_no} of ${gates.length}</span>` +
    `<span class="chip">${App.escape(q.subject)}</span>` +
    (q.attempt_no > 1 ? `<span class="chip chip-warning">Attempt ${q.attempt_no}</span>` : '');
  qText.textContent = q.prompt;
  optionsDiv.innerHTML = '';
  // Show options in random order but submit the original index.
  const order = q.options.map((text, idx) => ({ text, idx })).sort(() => Math.random() - 0.5);
  currentAnswers = order.map((opt, i) => {
    const btn = document.createElement('button');
    btn.className = 'answer';
    btn.innerHTML = `<span class="kbd">${i + 1}</span><span></span>`;
    btn.lastChild.textContent = opt.text;
    btn.onclick = async () => {
      if (btn.disabled) return;
      optionsDiv.querySelectorAll('button').forEach((b) => (b.disabled = true));
      btn.classList.add('is-picked');
      // The server ignores answers sent <1s after the question was served.
      const wait = 1100 - (Date.now() - shownAt);
      if (wait > 0) await sleep(wait);
      closeModal(popup);
      currentAnswers = [];
      await submitAnswer(gate, q.attempt_no, opt.idx);
    };
    optionsDiv.appendChild(btn);
    return btn;
  });
  openModal(popup);
}

async function submitAnswer(gate, attemptNo, option, retry = 0) {
  busy = true;
  try {
    const r = await App.api('/api/runs/answer', { body: { run_id: run.run_id, gate_no: gate.gate_no, attempt_no: attemptNo, option } });
    applyServer(r);
    if (r.correct) {
      wrongInARow = 0;
      toast(r.combo > 1 ? `Correct · +${r.coins} coins · ${r.combo}× combo` : `Correct · +${r.coins} coins`, 'success');
      bump('coins'); bump('xp');
    } else {
      wrongInARow++;
      const penalty = r.coins ? ` (${r.coins} coins)` : '';
      if (wrongInARow >= 3) {
        player = { ...start };
        wrongInARow = 0;
        toast(`Three wrong answers — back to the start${penalty}`, 'danger');
      } else {
        toast(`Not quite. ${3 - wrongInARow} ${3 - wrongInARow === 1 ? 'try' : 'tries'} left${penalty}`, 'danger');
      }
    }
  } catch (e) {
    // Network blip: resend the same attempt — the server de-duplicates it.
    if (e.code === 'NETWORK' && retry < 2) {
      await sleep(800);
      busy = false;
      return submitAnswer(gate, attemptNo, option, retry + 1);
    }
    reportError(e);
  } finally {
    busy = false;
    canvas.focus();
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
  if (nx === end.x && ny === end.y) completeLevel();
}

const KEY_DIRS = {
  ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
  w: [-1, 0], s: [1, 0], a: [0, -1], d: [0, 1], W: [-1, 0], S: [1, 0], A: [0, -1], D: [0, 1],
};
document.addEventListener('keydown', (e) => {
  if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
  if (e.key === 'Escape') {
    closeModal(shopPopup);
    if (!busy && popup.classList.contains('show')) { closeModal(popup); currentAnswers = []; }
    return;   // the finish dialog's buttons are the only way on
  }
  // 1–4: answer the open question, otherwise use a power-up.
  if (/^[1-4]$/.test(e.key)) {
    const n = Number(e.key) - 1;
    if (popup.classList.contains('show')) { currentAnswers[n]?.click(); return; }
    if (!inputBlocked()) usePower(POWERS[n]);
    return;
  }
  const dir = KEY_DIRS[e.key];
  if (!dir || !maze.length) return;
  e.preventDefault();
  tryMove(dir[0], dir[1]);
});

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
    player = { ...start };
    wrongInARow = 0;
    syncRun(r.run);
    applyServer(r);
    toast("Time's up — back to the start", 'danger');
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
  if (type === 'timeFreeze' && countdown === null) return toast('No timer on Easy — your time freeze is saved.');
  const target = type === 'skip' || type === 'hint' ? nearestPendingGate() : null;
  if ((type === 'skip' || type === 'hint') && !target) return toast('Every gate is already open.');

  try {
    const r = await App.api('/api/runs/powerup', {
      body: { run_id: run.run_id, type, gate_no: type === 'skip' ? target.gate_no : null },
    });
    applyServer(r);
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
(async function boot() {
  const params = new URLSearchParams(location.search);
  const d = (params.get('difficulty') || '').toLowerCase();
  if (['easy', 'medium', 'hard'].includes(d)) selectedDifficulty = d.charAt(0).toUpperCase() + d.slice(1);
  const subject = params.get('subject');
  if (!subject || !(CFG.subjects.includes(subject) || subject === 'Mixed Mode')) {
    location.replace(App.url('choose-subject') + (d ? `?difficulty=${d}` : ''));
    return;
  }
  selectedSubject = subject;
  $('runSubject').textContent = subject === 'Mixed Mode' ? 'Mixed mode' : subject;
  $('runDifficulty').textContent = `${selectedDifficulty} · ${diffCfg().size} × ${diffCfg().size}${diffCfg().fog ? ' · fog of war' : ''}`;
  try {
    wallet = (await App.api('/api/me')).wallet;
    updateHUD();
  } catch (e) {
    return reportError(e);
  }
  renderTimer();
  startRun();
})();
