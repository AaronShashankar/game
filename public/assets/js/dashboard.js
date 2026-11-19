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

  renderAchievements(data.achievements);
  renderLearning(data.learning);
  renderWeekly(data.weekly);
  renderDaily(daily);
  renderChart(data.xp_chart);
}

const fmtTime = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

function renderAchievements(list) {
  const unlocked = list.filter((a) => a.unlocked_at).sort((a, b) => b.unlocked_at.localeCompare(a.unlocked_at));
  $('achCount').textContent = `${unlocked.length} of ${list.length} unlocked`;
  $('achFill').style.width = `${Math.round((unlocked.length / list.length) * 100)}%`;
  // Latest unlocks, or the next few to aim for when there are none yet.
  const show = unlocked.length ? unlocked.slice(0, 3) : list.slice(0, 3);
  $('achRecent').innerHTML = show.map((a) => `
    <div class="list-row">
      <div class="icon-box${a.unlocked_at ? ' icon-box-accent' : ''}">${App.icon(a.icon)}</div>
      <div class="grow"><div class="list-title">${App.escape(a.name)}</div><div class="list-meta">${App.escape(a.description)}</div></div>
    </div>`).join('');
}

function renderLearning(learning) {
  $('reviewsDue').textContent = learning.reviews_due;
  $('reviewsLabel').textContent = learning.reviews_due === 1 ? 'question to revisit' : 'questions to revisit';
  // The three subjects with the most room to grow.
  const rows = [...learning.mastery].sort((a, b) => a.mastery - b.mastery).slice(0, 3);
  $('masteryPreview').innerHTML = rows.map((m) => `
    <div class="mastery-row">
      <div class="mastery-head"><span>${App.escape(m.subject)}</span><span>${m.mastery}% known</span></div>
      <div class="progress"><span style="width:${m.mastery}%"></span></div>
    </div>`).join('');
}

function renderWeekly(weekly) {
  const box = $('weeklyBody');
  const parts = [];
  if (weekly.you) {
    parts.push(`<div class="callout callout-accent" style="margin-top:0">${App.icon('trophy')}<div class="grow">You finished <strong>#${weekly.you.rank}</strong> last week and won <strong>${weekly.you.coins} coins</strong>.</div></div>`);
  }
  parts.push('<div class="list" id="weeklyMine"></div>');
  if (weekly.winners.length) {
    parts.push(`<div class="list-meta mt-16">Last week's winners</div>` + weekly.winners.map((w) => `
      <div class="list-row"><span class="rank rank-${w.rank}">${w.rank}</span>
        <div class="grow"><div class="list-title">${App.escape(w.username)}</div></div>
        <div class="list-value">+${w.coins}</div></div>`).join(''));
  }
  box.innerHTML = parts.join('');
  // Where you stand this week.
  App.api('/api/leaderboard?type=weekly&limit=100').then((d) => {
    const mine = d.leaderboard.find((p) => me && p.username === me.user.username);
    $('weeklyMine').innerHTML = `<div class="list-row"><div class="grow"><div class="list-title">This week</div>
      <div class="list-meta">${mine ? `${mine.xp.toLocaleString()} XP so far` : 'Earn XP to get on the board'}</div></div>
      <div class="list-value">${mine ? '#' + mine.rank : '—'}</div></div>`;
  }).catch(() => {});
}

async function loadDaily() {
  const box = $('dailyBody');
  try {
    const { daily: d } = await App.api('/api/daily');
    const you = d.you;
    let action;
    if (!you) {
      action = `<a class="btn btn-secondary btn-block" href="${App.url('game')}?daily=1">${App.icon('play')}Play today's maze</a>`;
    } else if (you.status === 'active') {
      action = `<a class="btn btn-secondary btn-block" href="${App.url('game')}?daily=1">${App.icon('play')}Resume</a>`;
    } else if (you.status === 'completed') {
      action = `<div class="callout callout-accent" style="margin-top:0">${App.icon('check')}<div class="grow">Done in <strong>${fmtTime(you.duration_seconds)}</strong> · #${you.rank} of ${d.finishers}</div></div>`;
    } else {
      action = '<div class="callout" style="margin-top:0">Come back tomorrow for a new maze.</div>';
    }
    const top = d.leaderboard.slice(0, 3).map((p) => `
      <div class="list-row"><span class="rank rank-${p.rank}">${p.rank}</span>
        <div class="grow"><div class="list-title">${App.escape(p.username)}</div></div>
        <div class="list-value">${fmtTime(p.duration_seconds)}</div></div>`).join('');
    box.innerHTML = `${action}
      <div class="list-meta mt-16">${d.finishers ? `${d.finishers} ${d.finishers === 1 ? 'player has' : 'players have'} finished · +${d.bonus_coins} coins bonus` : `Be the first to finish · +${d.bonus_coins} coins bonus`}</div>
      <div class="list">${top}</div>
      ${d.finishers > 3 ? `<a class="btn btn-ghost btn-sm mt-8" href="${App.url('leaderboard')}?type=daily">Full standings</a>` : ''}`;
  } catch (e) {
    box.innerHTML = `<div class="empty">Couldn't load today's maze.</div>`;
  }
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
  const freeze = $('freezeMeta');
  freeze.hidden = !daily.streak_freezes;
  freeze.textContent = `${daily.streak_freezes} streak ${daily.streak_freezes === 1 ? 'freeze' : 'freezes'} ready`;
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
  loadDaily();
  loadLeaderboard('xp');
}

// ---- Daily bonus & spin -----------------------------------------------------
async function claimBonus() {
  const btn = $('bonusBtn');
  btn.disabled = true;
  try {
    const r = await App.api('/api/checkin', { method: 'POST' });
    applyWallet(r.wallet);
    me.daily.checked_in = true;
    if (r.freezes_used) me.daily.streak_freezes -= r.freezes_used;
    renderDaily(me.daily);
    App.toast(r.claimed ? r.message : 'Already claimed today', r.claimed ? 'success' : '');
    (r.new_achievements || []).forEach((a) => App.toast('Achievement unlocked: ' + a.name, 'success'));
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
      content.innerHTML = `<div class="empty">${type === 'weekly' ? 'No XP earned this week yet' : 'No players yet'}</div>`;
      return;
    }
    content.innerHTML = data.leaderboard.map((p) => {
      const value = {
        weekly: `${p.xp.toLocaleString()} XP`,
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
