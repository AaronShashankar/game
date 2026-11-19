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
const optionsDiv = document.getElementById('options');
const levelPopup = document.getElementById('levelCompletePopup');
const correctAnswerPopup = document.getElementById('correctAnswerPopup');
const shopPopup = document.getElementById('shopPopup');

// Admin-tweakable colours (set from the admin panel in this browser).
let viewSettings = {};
try { viewSettings = JSON.parse(localStorage.getItem('mazeViewSettings') || '{}'); } catch (e) { /* ignore */ }
const WALL_COLOR = viewSettings.wallColor || '#5bc0be';
const LINE_WIDTH = typeof viewSettings.lineWidth === 'number' ? viewSettings.lineWidth : 4;

// ---- State --------------------------------------------------------------------
let wallet = null;             // coins, xp, level, inventory… from the server
let run = null;                // current run state from the server
let selectedSubject = '';
let selectedDifficulty = 'Easy';
let rows = 10, cols = 10, cellSize = canvas.width / cols;
let maze = [], gates = [], player = { x: 0, y: 0 };
let start = { x: 0, y: 0 }, end = { x: 9, y: 9 };
let countdown = null;          // seconds left (display); server is authoritative
let isTimeFrozen = false;
let levelComplete = false;
let busy = false;              // a request that should block input is in flight
let wrongInARow = 0;           // 3 wrong answers in a row sends you back to the start
let hintGate = null;
let sparkles = [];
let drawLoopStarted = false;
let runStartedAt = 0;

const diffCfg = () => CFG.difficulties[selectedDifficulty] || CFG.difficulties.Easy;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

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
function drawMaze() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = WALL_COLOR;
  ctx.lineWidth = LINE_WIDTH;
  for (let i = 0; i < rows; i++) for (let j = 0; j < cols; j++) {
    const x = j * cellSize, y = i * cellSize, c = maze[i]?.[j];
    if (!c) continue;
    ctx.beginPath();
    if (c.top) { ctx.moveTo(x, y); ctx.lineTo(x + cellSize, y); }
    if (c.right) { ctx.moveTo(x + cellSize, y); ctx.lineTo(x + cellSize, y + cellSize); }
    if (c.bottom) { ctx.moveTo(x, y + cellSize); ctx.lineTo(x + cellSize, y + cellSize); }
    if (c.left) { ctx.moveTo(x, y); ctx.lineTo(x, y + cellSize); }
    ctx.stroke();
  }
  for (const g of gates) {
    ctx.fillStyle = g.opened ? '#3a506b' : 'orange';
    ctx.fillRect(g.y * cellSize + cellSize / 4, g.x * cellSize + cellSize / 4, cellSize / 2, cellSize / 2);
    if (g.opened && Math.random() < 0.3) sparkles.push({ x: g.y * cellSize + cellSize / 2, y: g.x * cellSize + cellSize / 2, r: 2, alpha: 1 });
  }
  for (let i = sparkles.length - 1; i >= 0; i--) {
    const s = sparkles[i];
    ctx.fillStyle = `rgba(255,255,255,${s.alpha})`;
    ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fill();
    s.alpha -= 0.02; s.r += 0.3;
    if (s.alpha <= 0) sparkles.splice(i, 1);
  }
  // Start
  ctx.fillStyle = 'blue';
  ctx.fillRect(start.y * cellSize + cellSize / 4, start.x * cellSize + cellSize / 4, cellSize / 2, cellSize / 2);
  // Gift (exit)
  const gy = end.x * cellSize + cellSize / 2 + Math.sin(Date.now() / 300) * 5;
  const gx = end.y * cellSize + cellSize / 2;
  ctx.fillStyle = '#ff4d6d';
  ctx.fillRect(gx - cellSize / 3, gy - cellSize / 3, (cellSize * 2) / 3, (cellSize * 2) / 3);
  ctx.strokeStyle = '#ff8fa3'; ctx.lineWidth = 2;
  ctx.strokeRect(gx - cellSize / 3, gy - cellSize / 3, (cellSize * 2) / 3, (cellSize * 2) / 3);
  // Player (skin colour from owned cosmetics)
  const py = player.x * cellSize + cellSize / 2 + Math.sin(Date.now() / 200) * 2;
  const px = player.y * cellSize + cellSize / 2;
  const inv = wallet?.inventory || {};
  ctx.fillStyle = inv.skin3 ? 'red' : inv.skin2 ? 'cyan' : 'lime';
  ctx.beginPath(); ctx.arc(px, py, cellSize / 4, 0, Math.PI * 2); ctx.fill();

  // Fog of war (Hard)
  const fog = diffCfg().fog;
  if (fog && !levelComplete) {
    const r = fog * cellSize;
    const grad = ctx.createRadialGradient(px, py, r * 0.35, px, py, r);
    grad.addColorStop(0, 'rgba(5,8,12,0)');
    grad.addColorStop(1, 'rgba(5,8,12,0.97)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  }
  // Hint highlight sits above the fog
  if (hintGate) {
    ctx.strokeStyle = 'yellow'; ctx.lineWidth = 4;
    ctx.strokeRect(hintGate.y * cellSize + cellSize / 4, hintGate.x * cellSize + cellSize / 4, cellSize / 2, cellSize / 2);
  }
  requestAnimationFrame(drawMaze);
}

// ---- HUD / messages ---------------------------------------------------------------
function updateHUD() {
  if (!wallet) return;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  set('coins', wallet.coins);
  set('coinsHUD', wallet.coins);
  set('shopCoins', wallet.coins);
  set('xp', `${wallet.xp_into_level}/${wallet.xp_for_next}`);
  set('streak', wallet.streak);
  set('levelDisplay', wallet.level);
  for (const p of ['timeFreeze', 'hint', 'skip', 'scoreDoubler']) {
    const n = wallet.inventory[p] || 0;
    const cap = p.charAt(0).toUpperCase() + p.slice(1);
    set(p + 'Count', n);
    set('shop' + cap + 'Count', n);
    const btn = document.getElementById(p + 'Btn');
    if (btn) btn.disabled = n === 0;
  }
  document.getElementById('scoreDoublerBtn')?.classList.toggle('power-up-active', !!run?.doubler);
}

function renderTimer() {
  document.getElementById('timeLeft').textContent = countdown === null ? '∞' : Math.max(0, countdown);
  const box = document.getElementById('timer');
  box.classList.toggle('low-time', countdown !== null && countdown <= 10);
  box.classList.toggle('frozen', isTimeFrozen);
}

function showPopup(msg) {
  const el = document.createElement('div');
  el.className = 'popup show';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 2000);
}

function animateReward(type, value) {
  const el = document.getElementById(type);
  if (!el || !value) return;
  const fly = document.createElement('div');
  fly.className = 'coinXP';
  fly.textContent = (value > 0 ? '+' : '') + value;
  el.parentElement.appendChild(fly);
  setTimeout(() => fly.remove(), 1000);
}

function flashHUD() {
  document.querySelectorAll('#hud div').forEach((d) => { d.classList.add('flash'); setTimeout(() => d.classList.remove('flash'), 300); });
}

function applyServer(result) {
  if (result.wallet) wallet = result.wallet;
  if (result.run) syncRun(result.run);
  (result.new_badges || []).forEach((b) => showPopup(`🏅 Badge Unlocked: ${b}!`));
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
}

function reportError(e) {
  showPopup('⚠️ ' + (e.message || 'Something went wrong'));
}

// ---- Run lifecycle ----------------------------------------------------------------
function selectSubject(ev, subject) {
  document.querySelectorAll('.subject-card').forEach((c) => c.classList.remove('selected'));
  ev.currentTarget.classList.add('selected');
  selectedSubject = subject;
  document.getElementById('startGameBtn').style.display = 'block';
}

function startGameWithSubject() {
  if (!selectedSubject) return showPopup('Please select a subject first! 📚');
  document.getElementById('subjectSelection').style.display = 'none';
  document.getElementById('gameSection').style.display = 'block';
  startRun();
}

async function startRun(replayOf = null) {
  busy = true;
  try {
    const r = await App.api('/api/runs/start', {
      body: { difficulty: selectedDifficulty, subject: selectedSubject, replay_of: replayOf },
    });
    const cfg = diffCfg();
    rows = cols = cfg.size;
    cellSize = canvas.width / cols;
    start = { x: 0, y: 0 };
    end = { x: rows - 1, y: cols - 1 };
    player = { ...start };
    levelComplete = false;
    wrongInARow = 0;
    hintGate = null;
    sparkles = [];
    generateMaze(r.run.seed);
    placeGates(r.run.gate_count, r.run.gates);
    syncRun(r.run);
    runStartedAt = Date.now();
    updateHUD();
    if (!drawLoopStarted) { drawLoopStarted = true; drawMaze(); }
    canvas.focus();
    showPopup(`Playing: ${selectedSubject} (${selectedDifficulty}) 🎯`);
  } catch (e) {
    reportError(e);
  } finally {
    busy = false;
  }
}

function nextLevel() {
  levelPopup.classList.remove('show');
  startRun();
}

// Same maze layout, fresh run (gates closed again).
function restartLevel() {
  levelPopup.classList.remove('show');
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
        showPopup('Checking your run…');
        await sleep(Math.max(1000, diffCfg().min_seconds * 1000 - (Date.now() - runStartedAt)));
      }
    }
    applyServer(r);
    levelPopup.querySelector('p').textContent =
      `+${r.coins} coins, +${r.xp} XP` + (r.time_bonus ? ` (includes +${r.time_bonus} time bonus)` : '');
    levelPopup.classList.add('show');
    animateReward('coins', r.coins);
    animateReward('xp', r.xp);
    if (r.level_up) showPopup(`🎉 Level Up! You are now Level ${r.level_up}!`);
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
    if (e.code === 'GATE_NOT_PENDING') { gate.opened = true; return; }
    return reportError(e);
  }
  busy = false;
  const shownAt = Date.now();

  qText.innerText = `Subject: ${q.subject}\n${q.prompt}`;
  optionsDiv.innerHTML = '';
  // Show options in random order but submit the original index.
  const order = q.options.map((text, idx) => ({ text, idx })).sort(() => Math.random() - 0.5);
  for (const opt of order) {
    const btn = document.createElement('button');
    btn.textContent = opt.text;
    btn.onclick = async () => {
      optionsDiv.querySelectorAll('button').forEach((b) => (b.disabled = true));
      // The server ignores answers sent <1s after the question was served.
      const wait = 1100 - (Date.now() - shownAt);
      if (wait > 0) await sleep(wait);
      popup.classList.remove('show');
      await submitAnswer(gate, q.attempt_no, opt.idx);
    };
    optionsDiv.appendChild(btn);
  }
  popup.classList.add('show');
}

async function submitAnswer(gate, attemptNo, option, retry = 0) {
  busy = true;
  try {
    const r = await App.api('/api/runs/answer', { body: { run_id: run.run_id, gate_no: gate.gate_no, attempt_no: attemptNo, option } });
    applyServer(r);
    if (r.correct) {
      wrongInARow = 0;
      correctAnswerPopup.querySelector('.reward-amount').textContent =
        r.combo > 1 ? `+${r.coins} (🔥 x${r.combo} combo)` : `+${r.coins}`;
      correctAnswerPopup.classList.add('show');
      setTimeout(() => correctAnswerPopup.classList.remove('show'), 1200);
      animateReward('coins', r.coins);
      animateReward('xp', r.xp);
      flashHUD();
    } else {
      wrongInARow++;
      if (r.coins) animateReward('coins', r.coins);
      const penalty = r.coins ? ` (${r.coins} coins)` : '';
      if (wrongInARow >= 3) {
        player = { ...start };
        wrongInARow = 0;
        showPopup(`⚠️ 3 wrong answers — back to the start!${penalty}`);
      } else {
        showPopup(`❌ Wrong answer! ${3 - wrongInARow} tries left.${penalty}`);
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
  if (e.key === 'Escape') {
    popup.classList.remove('show');
    correctAnswerPopup.classList.remove('show');
    shopPopup.classList.remove('show');
    if (!levelComplete) levelPopup.classList.remove('show');  // its buttons are the only way on
    return;
  }
  const dir = KEY_DIRS[e.key];
  if (!dir || !maze.length) return;
  if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
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
    popup.classList.remove('show');
    player = { ...start };
    wrongInARow = 0;
    syncRun(r.run);
    showPopup("⏳ Time's up! Back to the start.");
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
    showPopup(`No ${type} power-ups left — visit the shop!`);
    return showShop();
  }
  if (type === 'timeFreeze' && countdown === null) return showPopup('⏱ No timer on Easy — saved your Time Freeze.');
  const target = type === 'skip' || type === 'hint' ? nearestPendingGate() : null;
  if ((type === 'skip' || type === 'hint') && !target) return showPopup('No unopened gates left!');

  try {
    const r = await App.api('/api/runs/powerup', {
      body: { run_id: run.run_id, type, gate_no: type === 'skip' ? target.gate_no : null },
    });
    applyServer(r);
    if (type === 'timeFreeze') {
      showPopup(`⏱ Timer frozen for ${r.effect.frozen_seconds} seconds!`);
    } else if (type === 'hint') {
      hintGate = target;
      showPopup('🔍 Nearest gate highlighted in yellow!');
      setTimeout(() => (hintGate = null), 3000);
    } else if (type === 'skip') {
      flashHUD();
      showPopup('✅ Skip Gate used — nearest gate opened.');
    } else if (type === 'scoreDoubler') {
      showPopup('💰 Score Doubler active for your next correct answer!');
    }
  } catch (e) {
    reportError(e);
  }
}

// ---- In-game shop -------------------------------------------------------------------
function showShop() { updateHUD(); shopPopup.classList.add('show'); }
function hideShop() { shopPopup.classList.remove('show'); }

async function buyItem(code, label) {
  try {
    const r = await App.api('/api/shop/buy', { body: { item: code } });
    applyServer(r);
    flashHUD();
    showPopup(`✅ Purchased ${label}!`);
  } catch (e) {
    reportError(e);
  }
}
// Prices in the markup are display-only; the server charges its own price.
const buyPowerUp = (type) => buyItem(type, type);
const buyPowerUpPack = () => buyItem('pack', 'Power-Up Pack');
const buyMegaPack = () => buyItem('megaPack', 'Mega Pack');

// ---- Boot ---------------------------------------------------------------------------
(async function boot() {
  try {
    wallet = (await App.api('/api/me')).wallet;
    updateHUD();
  } catch (e) {
    return reportError(e);
  }
  renderTimer();
  const params = new URLSearchParams(location.search);
  const d = (params.get('difficulty') || '').toLowerCase();
  if (['easy', 'medium', 'hard'].includes(d)) selectedDifficulty = d.charAt(0).toUpperCase() + d.slice(1);
  const subject = params.get('subject');
  if (subject && (CFG.subjects.includes(subject) || subject === 'Mixed Mode')) {
    selectedSubject = subject;
    document.getElementById('subjectSelection').style.display = 'none';
    document.getElementById('gameSection').style.display = 'block';
    startRun();
  } else {
    document.getElementById('subjectSelection').style.display = 'block';
  }
})();
