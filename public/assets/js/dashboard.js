// Player dashboard. All numbers come from the server; actions (daily bonus,
// spin) are server calls — nothing is computed here.
let me = null;
let chart = null;

const $ = (id) => document.getElementById(id);

function render(data) {
  me = data;
  const { user, wallet, daily } = data;
  $('profileUsername').textContent = user.username;
  renderWallet(wallet);

  const answered = user.correct_answers + user.wrong_answers;
  $('accuracy').textContent = answered ? Math.round((user.correct_answers / answered) * 100) + '%' : '—';
  $('gamesPlayed').textContent = user.total_games;

  $('badges').innerHTML = user.badges.length
    ? user.badges.map((b) => `<span class="chip chip-accent">${App.icon('award')}${App.escape(b)}</span>`).join('')
    : '<span class="subtle" style="font-size:14px">No badges yet. Reach level 5, save 500 coins or keep a 10-day streak.</span>';

  renderDaily(daily);
  renderChart(data.xp_chart);
}

function renderWallet(wallet) {
  $('coins').textContent = wallet.coins.toLocaleString();
  $('level').textContent = wallet.level;
  $('xp').textContent = `${wallet.xp_into_level} / ${wallet.xp_for_next}`;
  $('xpFill').style.width = Math.min(100, (wallet.xp_into_level / wallet.xp_for_next) * 100) + '%';
  $('streak').textContent = wallet.streak;
  App.setCoins(wallet.coins);
}

function renderDaily(daily) {
  const btn = $('bonusBtn');
  btn.disabled = daily.checked_in;
  btn.textContent = daily.checked_in ? 'Claimed' : 'Claim';
  $('bonusMeta').textContent = daily.checked_in ? 'Come back tomorrow' : 'Keep your streak going';
  $('spinsLeft').textContent = daily.spins_left;
  $('spinsLeftMeta').textContent = `${daily.spins_left} of 3`;
  $('spinWheelBtn').disabled = daily.spins_left <= 0;
}

// Updates from action responses that only carry the wallet.
function applyWallet(wallet) {
  if (!me) return;
  me.wallet = wallet;
  renderWallet(wallet);
}

function chartColors() {
  const css = getComputedStyle(document.documentElement);
  return { accent: css.getPropertyValue('--accent').trim(), grid: css.getPropertyValue('--border').trim(), text: css.getPropertyValue('--text-3').trim() };
}

function renderChart(points) {
  if (typeof Chart === 'undefined') {
    // Chart.js loads deferred; draw once it's there (or skip if the CDN is blocked).
    if (document.readyState !== 'complete') {
      window.addEventListener('load', () => typeof Chart !== 'undefined' && renderChart(points), { once: true });
    }
    return;
  }
  const labels = points.map((p) => new Date(p.day + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short' }));
  const values = points.map((p) => p.xp);
  const c = chartColors();
  if (chart) chart.destroy();
  chart = new Chart($('progressChart').getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'XP', data: values, backgroundColor: c.accent, borderRadius: 6, maxBarThickness: 32 }] },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { displayColors: false } },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: c.text } },
        y: { beginAtZero: true, grid: { color: c.grid }, border: { display: false }, ticks: { color: c.text, precision: 0 } },
      },
    },
  });
}
window.addEventListener('themechange', () => me && renderChart(me.xp_chart));

async function load() {
  try {
    render(await App.api('/api/me'));
  } catch (e) {
    App.toast(e.message, 'danger');
  }
  loadLeaderboard('xp');
}

// ---- Daily bonus & spin -----------------------------------------------------
async function claimBonus() {
  const btn = $('bonusBtn');
  btn.disabled = true;
  try {
    const r = await App.api('/api/checkin', { method: 'POST' });
    applyWallet(r.wallet);
    renderDaily({ ...me.daily, checked_in: true });
    me.daily.checked_in = true;
    App.toast(r.claimed ? `+${r.coins} coins · ${r.streak}-day streak` : 'Already claimed today', r.claimed ? 'success' : '');
    (r.new_badges || []).forEach((b) => App.toast('Badge unlocked: ' + b, 'success'));
  } catch (e) {
    btn.disabled = false;
    App.toast(e.message, 'danger');
  }
}

function openSpinWheel() { $('spinModal').classList.add('show'); }
function closeSpinWheel() { if (!spinning) $('spinModal').classList.remove('show'); }

let spinning = false;
let wheelTurns = 0;
async function spinWheel() {
  if (spinning) return;
  spinning = true;
  $('spinWheelBtn').disabled = true;
  $('spinResult').innerHTML = '&nbsp;';
  try {
    // The server picks the prize; the wheel just animates to it.
    const r = await App.api('/api/spin', { method: 'POST' });
    // Label i is drawn at 120° + 60°·i clockwise from the top pointer, so
    // rotating by (240 − 60·i) mod 360 brings it under the pointer.
    wheelTurns += 4;
    const deg = wheelTurns * 360 + (((240 - r.segment * 60) % 360) + 360) % 360;
    $('wheel').style.transform = `rotate(${deg}deg)`;
    setTimeout(() => {
      $('spinResult').textContent = `You won ${r.prize}`;
      applyWallet(r.wallet);
      me.daily.spins_left = r.spins_left;
      renderDaily(me.daily);
      spinning = false;
    }, 4000);
  } catch (e) {
    $('spinResult').textContent = e.message;
    spinning = false;
    $('spinWheelBtn').disabled = e.code === 'SPIN_LIMIT';
  }
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSpinWheel(); });
$('spinModal').addEventListener('click', (e) => { if (e.target.id === 'spinModal') closeSpinWheel(); });

// ---- Leaderboard ------------------------------------------------------------
async function loadLeaderboard(type = 'xp') {
  const content = $('leaderboardContent');
  document.querySelectorAll('.lb-btn').forEach((b) => b.classList.toggle('is-active', b.id === type + 'Btn'));
  try {
    const data = await App.api(`/api/leaderboard?type=${encodeURIComponent(type)}&limit=5`);
    if (!data.leaderboard.length) {
      content.innerHTML = '<div class="empty">No players yet</div>';
      return;
    }
    content.innerHTML = data.leaderboard.map((p) => {
      const value = {
        coins: `${p.coins.toLocaleString()} coins`,
        level: `Level ${p.level}`,
        streak: `${p.streak} days`,
      }[type] || `${p.xp.toLocaleString()} XP`;
      const isMe = me && p.username === me.user.username;
      return `
        <div class="list-row">
          <span class="rank rank-${p.rank}">${p.rank}</span>
          <img src="${App.escape(App.avatar(p.avatar, p.username))}" class="avatar" alt="">
          <div class="grow">
            <div class="list-title">${App.escape(p.username)}${isMe ? ' <span class="chip chip-accent">You</span>' : ''}</div>
            <div class="list-meta">Level ${p.level}</div>
          </div>
          <div class="list-value">${value}</div>
        </div>`;
    }).join('');
  } catch (e) {
    content.innerHTML = `<div class="empty">Couldn't load the leaderboard.</div>`;
  }
}

load();
if (new URLSearchParams(location.search).get('openSpin') === '1') openSpinWheel();
