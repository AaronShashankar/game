// Player dashboard. All numbers come from the server; actions (daily bonus,
// spin, profile edits) are server calls — nothing is computed here.
let me = null;
let chart = null;

const $ = (id) => document.getElementById(id);

function showPopup(msg) {
  const popup = $('popup');
  popup.textContent = msg;
  popup.classList.add('show');
  clearTimeout(showPopup.t);
  showPopup.t = setTimeout(() => popup.classList.remove('show'), 2500);
}

function render(data) {
  me = data;
  const { user, wallet } = data;
  $('profileUsername').textContent = user.username;
  $('profileEmail').textContent = user.email;
  $('profileAvatar').src = App.avatar(user.avatar, user.username);
  $('coins').textContent = wallet.coins;
  $('coinSticker').textContent = wallet.coins;
  $('level').textContent = 'Level ' + wallet.level;
  $('xp').textContent = `${wallet.xp_into_level} / ${wallet.xp_for_next}`;
  $('xpFill').style.width = Math.min(100, (wallet.xp_into_level / wallet.xp_for_next) * 100) + '%';
  $('streak').textContent = wallet.streak;
  $('badges').textContent = user.badges.length ? user.badges.join(', ') : 'No badges yet';
  renderChart(data.xp_chart);
}

// Updates from action responses that only carry the wallet.
function applyWallet(wallet) {
  if (!me) return;
  me.wallet = wallet;
  render(me);
}

function renderChart(points) {
  if (typeof Chart === 'undefined') return;   // CDN blocked: skip the chart
  const labels = points.map((p) => new Date(p.day + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short' }));
  const values = points.map((p) => p.xp);
  if (chart) {
    chart.data.labels = labels;
    chart.data.datasets[0].data = values;
    chart.update();
    return;
  }
  chart = new Chart($('progressChart').getContext('2d'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'XP earned', data: values, borderColor: '#ff6b6b', tension: 0.3 }] },
    options: { scales: { y: { beginAtZero: true } } },
  });
}

async function load() {
  try {
    render(await App.api('/api/me'));
    loadLeaderboard('xp');
  } catch (e) {
    showPopup('⚠️ ' + e.message);
  }
}

// ---- Profile panel ------------------------------------------------------------
function openProfilePanel() {
  $('profilePanel').style.display = 'block';
  $('panelUsername').value = me.user.username;
  $('panelEmail').value = me.user.email;
  $('panelAvatar').src = App.avatar(me.user.avatar, me.user.username);
  $('panelCurrentPassword').value = '';
  $('panelNewPassword').value = '';
}

function closeProfilePanel() {
  $('profilePanel').style.display = 'none';
}

async function saveProfilePanel() {
  try {
    render(await App.api('/api/me', {
      body: { username: $('panelUsername').value.trim(), email: $('panelEmail').value.trim() },
    }));
    const current = $('panelCurrentPassword').value;
    const next = $('panelNewPassword').value;
    if (current || next) {
      await App.api('/api/auth/password', { body: { current_password: current, new_password: next } });
    }
    showPopup('✅ Profile updated!');
    closeProfilePanel();
  } catch (e) {
    showPopup('❌ ' + e.message);
  }
}

// Shrink uploads to a 128px JPEG so avatars stay small.
function resizeImage(file) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const size = 128;
      const c = document.createElement('canvas');
      c.width = c.height = size;
      const scale = Math.max(size / img.width, size / img.height);
      const w = img.width * scale, h = img.height * scale;
      c.getContext('2d').drawImage(img, (size - w) / 2, (size - h) / 2, w, h);
      URL.revokeObjectURL(img.src);
      resolve(c.toDataURL('image/jpeg', 0.85));
    };
    img.onerror = () => reject(new Error('That file is not an image'));
    img.src = URL.createObjectURL(file);
  });
}

async function uploadAvatar(file) {
  if (!file) return;
  try {
    render(await App.api('/api/me', { body: { avatar: await resizeImage(file) } }));
    $('panelAvatar').src = App.avatar(me.user.avatar, me.user.username);
    showPopup('✅ Profile picture updated!');
  } catch (e) {
    showPopup('❌ ' + e.message);
  }
}
const handleImageUpload = (e) => uploadAvatar(e.target.files[0]);
const handlePanelImageUpload = (e) => uploadAvatar(e.target.files[0]);

// ---- Toggles ------------------------------------------------------------------
function toggleDarkMode() {
  toggleTheme();
  showPopup(document.body.getAttribute('data-theme') === 'dark' ? '🌙 Dark Mode On' : '☀️ Light Mode On');
}

let musicOn = false;
function toggleMusic() {
  const music = $('bgMusic');
  musicOn = !musicOn;
  // Browsers block autoplay, so music only starts from this click.
  if (musicOn) music.play().catch(() => showPopup('Could not play music'));
  else music.pause();
  showPopup(musicOn ? '🔊 Music on' : '🔇 Music off');
}

// ---- Game, bonus, spin --------------------------------------------------------
function startGame(difficulty) {
  location.href = `${App.url('choose-subject')}?difficulty=${encodeURIComponent(difficulty)}`;
}

async function claimBonus() {
  try {
    const r = await App.api('/api/checkin', { method: 'POST' });
    applyWallet(r.wallet);
    showPopup(r.claimed ? '🎁 ' + r.message : '✅ Already claimed today!');
    (r.new_badges || []).forEach((b) => showPopup('🏅 Badge unlocked: ' + b));
  } catch (e) {
    showPopup('❌ ' + e.message);
  }
}

function openSpinWheel() { $('spinModal').style.display = 'block'; }
function closeSpinWheel() { $('spinModal').style.display = 'none'; }

let spinning = false;
let wheelTurns = 0;
async function spinWheel() {
  if (spinning) return;
  spinning = true;
  $('spinWheelBtn').disabled = true;
  try {
    // The server picks the prize; the wheel just animates to it.
    const r = await App.api('/api/spin', { method: 'POST' });
    $('spinsLeft').textContent = r.spins_left;
    // Label i is drawn at 120° + 60°·i clockwise from the top pointer, so
    // rotating by (240 − 60·i) mod 360 brings it under the pointer.
    wheelTurns += 3;
    const deg = wheelTurns * 360 + (((240 - r.segment * 60) % 360) + 360) % 360;
    $('wheel').style.transform = `rotate(${deg}deg)`;
    setTimeout(() => {
      $('spinResult').textContent = `🎉 You won: ${r.prize}`;
      applyWallet(r.wallet);
      spinning = false;
      $('spinWheelBtn').disabled = r.spins_left <= 0;
    }, 4000);
  } catch (e) {
    $('spinResult').textContent = e.message;
    spinning = false;
    $('spinWheelBtn').disabled = e.code === 'SPIN_LIMIT';
  }
}

// ---- Leaderboard --------------------------------------------------------------
async function loadLeaderboard(type = 'xp') {
  const content = $('leaderboardContent');
  document.querySelectorAll('.lb-btn').forEach((b) => b.classList.remove('active'));
  $(type + 'Btn')?.classList.add('active');
  content.innerHTML = '<p style="text-align:center;color:#999;">Loading...</p>';
  try {
    const data = await App.api(`/api/leaderboard?type=${encodeURIComponent(type)}&limit=10`);
    if (!data.leaderboard.length) {
      content.innerHTML = '<p style="text-align:center;color:#999;">No players yet</p>';
      return;
    }
    content.innerHTML = data.leaderboard.map((p) => {
      const rankClass = ['top1', 'top2', 'top3'][p.rank - 1] || '';
      const medal = ['🥇', '🥈', '🥉'][p.rank - 1] || p.rank;
      const [value, stats] = {
        coins:  [`${p.coins} 🪙`, `Level ${p.level} • ${p.xp} XP`],
        level:  [`Lvl ${p.level}`, `${p.xp} XP • ${p.coins} coins`],
        streak: [`${p.streak} 🔥`, `Level ${p.level} • ${p.coins} coins`],
      }[type] || [`${p.xp} XP`, `Level ${p.level} • ${p.coins} coins`];
      return `
        <div class="lb-item">
          <div class="lb-rank ${rankClass}">${medal}</div>
          <img src="${App.escape(App.avatar(p.avatar, p.username))}" class="lb-avatar" alt="">
          <div class="lb-info">
            <div class="lb-username">${App.escape(p.username)}</div>
            <div class="lb-stats">${stats}</div>
          </div>
          <div class="lb-value">${value}</div>
        </div>`;
    }).join('');
  } catch (e) {
    content.innerHTML = `<p style="text-align:center;color:#f00;">Error: ${App.escape(e.message)}</p>`;
  }
}

load();
if (new URLSearchParams(location.search).get('openSpin') === '1') openSpinWheel();
