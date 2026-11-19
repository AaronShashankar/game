// Profile page: everything shown comes from /api/me; only username, email,
// avatar and password can be changed.
let me = null;

function render(data) {
  me = data;
  const { user, wallet } = data;
  const $ = (id) => document.getElementById(id);

  $('coins').textContent = wallet.coins;
  $('level').textContent = `Level ${wallet.level}`;
  $('currentXP').textContent = wallet.xp_into_level;
  $('nextLevelXP').textContent = wallet.xp_for_next;
  const pct = Math.min(100, Math.round((wallet.xp_into_level / wallet.xp_for_next) * 100));
  $('xpPercent').textContent = pct + '%';
  $('xpFill').style.width = pct + '%';
  $('streak').textContent = wallet.streak;
  $('avatar').src = App.avatar(user.avatar, user.username);

  for (const p of ['timeFreeze', 'hint', 'skip', 'scoreDoubler']) {
    $(p + 'Count').textContent = wallet.inventory[p] || 0;
  }

  const badges = $('badges');
  badges.innerHTML = '';
  user.badges.forEach((b) => {
    const el = document.createElement('div');
    el.className = 'badge';
    el.textContent = b;
    badges.appendChild(el);
  });
  $('noBadges').style.display = user.badges.length ? 'none' : 'block';

  $('username').value = user.username;
  $('email').value = user.email;
  $('headerUserName').textContent = user.username;
  $('headerUserEmail').textContent = user.email;

  // Referrals were never implemented on the server.
  $('referralCodeInput').value = 'Coming soon';
  $('friendsInvited').textContent = $('coinsEarned').textContent = $('pendingRewards').textContent = '0';
}

async function load() {
  try {
    render(await App.api('/api/me'));
  } catch (e) {
    alert('Could not load your profile: ' + e.message);
  }
}

async function saveProfile() {
  const newPassword = document.getElementById('password').value;
  try {
    render(await App.api('/api/me', {
      body: {
        username: document.getElementById('username').value.trim(),
        email: document.getElementById('email').value.trim(),
      },
    }));
    if (newPassword) {
      const current = prompt('Enter your current password to change it:');
      if (current) {
        await App.api('/api/auth/password', { body: { current_password: current, new_password: newPassword } });
      }
    }
    document.getElementById('password').value = '';
    alert('Profile updated! ✅');
  } catch (e) {
    alert('⚠️ ' + e.message);
  }
}

// New random avatar from a set of generated images (no third-party service).
async function changeAvatar() {
  const colours = ['#5bc0be', '#ff6b6b', '#ffd166', '#06d6a0', '#118ab2', '#9b5de5'];
  const faces = ['🦊', '🐼', '🐸', '🦁', '🐙', '🐵', '🐧', '🦉'];
  const pick = (a) => a[Math.floor(Math.random() * a.length)];
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="32" fill="${pick(colours)}"/>` +
              `<text x="32" y="44" font-size="34" text-anchor="middle">${pick(faces)}</text></svg>`;
  // Encode as base64 data URL so the server's avatar validation accepts it.
  const bytes = new TextEncoder().encode(svg);
  const avatar = 'data:image/svg+xml;base64,' + btoa(String.fromCharCode(...bytes));
  try {
    render(await App.api('/api/me', { body: { avatar } }));
  } catch (e) {
    alert('⚠️ ' + e.message);
  }
}

function copyReferralCode() {
  alert('Referral codes are coming soon!');
}

function toggleDarkMode() {
  toggleTheme();
}

function toggleMusic() {
  alert('There is no music on this page.');
}

function goDashboard() {
  location.href = App.url('dashboard');
}

load();
