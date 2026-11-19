// Profile page: everything shown comes from /api/me; only username, email,
// avatar and password can be changed.
const $ = (id) => document.getElementById(id);

function render(data) {
  const { user, wallet } = data;

  $('coins').textContent = wallet.coins.toLocaleString();
  App.setCoins(wallet.coins);
  $('level').textContent = wallet.level;
  $('currentXP').textContent = wallet.xp_into_level;
  $('nextLevelXP').textContent = wallet.xp_for_next;
  const pct = Math.min(100, Math.round((wallet.xp_into_level / wallet.xp_for_next) * 100));
  $('xpPercent').textContent = pct + '%';
  $('xpFill').style.width = pct + '%';
  $('streak').textContent = wallet.streak;
  $('streakUnit').textContent = wallet.streak === 1 ? 'day' : 'days';
  $('games').textContent = user.total_games;
  const answered = user.correct_answers + user.wrong_answers;
  $('accuracy').textContent = answered ? Math.round((user.correct_answers / answered) * 100) + '%' : '—';
  $('avatar').src = App.avatar(user.avatar, user.username);

  for (const p of ['timeFreeze', 'hint', 'skip', 'scoreDoubler']) {
    $(p + 'Count').textContent = wallet.inventory[p] || 0;
  }

  $('badges').innerHTML = user.badges
    .map((b) => `<span class="chip chip-accent">${App.icon('award')}${App.escape(b)}</span>`).join('');
  $('noBadges').hidden = user.badges.length > 0;

  $('username').value = user.username;
  $('email').value = user.email;
  $('headerUserName').textContent = user.username;
  $('headerUserEmail').textContent = user.email;
}

async function load() {
  try {
    render(await App.api('/api/me'));
  } catch (e) {
    App.toast('Could not load your profile: ' + e.message, 'danger');
  }
}

async function withButton(form, work) {
  const btn = form.querySelector('[type=submit]');
  btn.disabled = true;
  try { await work(); } finally { btn.disabled = false; }
}

$('accountForm').addEventListener('submit', (e) => {
  e.preventDefault();
  withButton(e.target, async () => {
    try {
      render(await App.api('/api/me', { body: { username: $('username').value.trim(), email: $('email').value.trim() } }));
      App.toast('Profile saved', 'success');
    } catch (err) {
      App.toast(err.message, 'danger');
    }
  });
});

$('passwordForm').addEventListener('submit', (e) => {
  e.preventDefault();
  if ($('password').value !== $('passwordConfirm').value) {
    App.toast("New passwords don't match", 'danger');
    return;
  }
  withButton(e.target, async () => {
    try {
      await App.api('/api/auth/password', { body: { current_password: $('currentPassword').value, new_password: $('password').value } });
      e.target.reset();
      App.toast('Password updated', 'success');
    } catch (err) {
      App.toast(err.message, 'danger');
    }
  });
});

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

async function saveAvatar(avatar) {
  try {
    render(await App.api('/api/me', { body: { avatar } }));
    App.toast('Avatar updated', 'success');
  } catch (e) {
    App.toast(e.message, 'danger');
  }
}

async function uploadAvatar(file) {
  if (!file) return;
  try {
    await saveAvatar(await resizeImage(file));
  } catch (e) {
    App.toast(e.message, 'danger');
  }
}

// A generated geometric avatar (no third-party service).
function changeAvatar() {
  const hues = [230, 200, 160, 20, 280, 340];
  const pick = (a) => a[Math.floor(Math.random() * a.length)];
  const h = pick(hues);
  const cells = [];
  for (let y = 0; y < 5; y++) for (let x = 0; x < 3; x++) {
    if (Math.random() < 0.5) {
      cells.push(`<rect x="${13 + x * 8}" y="${13 + y * 8}" width="8" height="8"/>`);
      if (x < 2) cells.push(`<rect x="${13 + (4 - x) * 8}" y="${13 + y * 8}" width="8" height="8"/>`);
    }
  }
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="66" height="66" viewBox="0 0 66 66">` +
    `<rect width="66" height="66" fill="hsl(${h},40%,94%)"/><g fill="hsl(${h},55%,48%)">${cells.join('')}</g></svg>`;
  // Encode as base64 data URL so the server's avatar validation accepts it.
  const bytes = new TextEncoder().encode(svg);
  saveAvatar('data:image/svg+xml;base64,' + btoa(String.fromCharCode(...bytes)));
}

load();
