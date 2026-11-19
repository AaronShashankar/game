// Admin panel. Access is enforced by the server (the page and every
// /api/admin/* endpoint require an admin session) — nothing here decides it.
const esc = (v) => App.escape(v);
const $ = (id) => document.getElementById(id);
const when = (s) => new Date(String(s).replace(' ', 'T'));
const fmt = (n) => Number(n || 0).toLocaleString();

function showError(message) {
  App.toast(message, 'danger');
}

// ---- Tabs ---------------------------------------------------------------------
const LOADERS = { dashboard: loadDashboardData, users: loadUsersTable, security: loadSecurityLogs, analytics: loadAnalytics };
let currentTab = 'dashboard';

function switchTab(tabName) {
  currentTab = tabName;
  document.querySelectorAll('.tab-content').forEach((t) => t.classList.toggle('active', t.id === tabName + '-tab'));
  document.querySelectorAll('.tab-btn').forEach((b) => b.classList.toggle('is-active', b.dataset.tab === tabName));
  $('tabTitle').textContent = document.querySelector(`.tab-btn[data-tab="${tabName}"] span`).textContent;
  (LOADERS[tabName] || (() => {}))();
}
document.querySelectorAll('.tab-btn').forEach((b) => b.addEventListener('click', () => switchTab(b.dataset.tab)));

// ---- Overview -------------------------------------------------------------------
async function loadDashboardData() {
  try {
    const { stats } = await App.api('/api/admin/stats');
    $('totalUsers').textContent = fmt(stats.totalUsers);
    $('totalGames').textContent = fmt(stats.totalGames);
    $('totalCoins').textContent = fmt(stats.totalCoins);
    $('activeUsers').textContent = fmt(stats.activeUsers);
    $('avgLevel').textContent = stats.avgLevel;
    $('totalXP').textContent = fmt(stats.totalXP);
    $('gatesOpened').textContent = fmt(stats.totalGates);
    $('correctAnswers').textContent = fmt(stats.totalCorrect);
    loadRecentActivity();
  } catch (e) {
    showError('Failed to load dashboard data: ' + e.message);
  }
}

async function loadRecentActivity() {
  const box = $('recentActivity');
  try {
    const { activity } = await App.api('/api/admin/activity');
    box.innerHTML = activity.length ? activity.map((a) => `
      <div class="log-entry">
        <span class="dot ${a.type === 'info' ? 'dot-accent' : 'dot-success'}"></span>
        <div class="grow">${esc(a.message)}</div>
        <time>${esc(when(a.time).toLocaleString())}</time>
      </div>`).join('') : '<div class="empty">No activity yet.</div>';
  } catch (e) {
    box.innerHTML = '<div class="empty">Failed to load recent activity.</div>';
  }
}

// ---- Players ------------------------------------------------------------------
async function loadUsersTable() {
  const body = $('usersTableBody');
  try {
    const { users } = await App.api('/api/admin/users');
    if (!users.length) {
      body.innerHTML = '<tr><td colspan="11" class="empty">No players yet.</td></tr>';
      return;
    }
    body.innerHTML = '';
    users.forEach((u) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><strong style="font-weight:500">${esc(u.username)}</strong></td>
        <td class="num">${u.level}</td><td class="num">${fmt(u.coins)}</td><td class="num">${fmt(u.xp)}</td><td class="num">${u.streak}</td>
        <td class="num">${u.total_games}</td><td class="num">${u.powerups_used}</td><td class="num">${u.correct_answers}</td><td class="num">${u.wrong_answers}</td>
        <td class="subtle">${u.last_active ? esc(when(u.last_active).toLocaleDateString()) : '—'}</td>
        <td style="text-align:right"><button class="btn btn-danger btn-sm">${App.icon('trash', 'icon-sm')}Delete</button></td>`;
      // Username goes through a closure, not an inline onclick string.
      row.querySelector('button').addEventListener('click', () => deleteUser(u.id, u.username));
      body.appendChild(row);
    });
  } catch (e) {
    body.innerHTML = `<tr><td colspan="11" class="empty">${esc(e.message)}</td></tr>`;
  }
}

$('addUserForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    await App.api('/api/admin/users', {
      body: {
        username: $('newUsername').value.trim(),
        password: $('newUserPassword').value,
        email: $('newEmail').value.trim(),
        coins: Number($('initialCoins').value || 0),
        level: Number($('initialLevel').value || 1),
      },
    });
    App.toast('Player added', 'success');
    closeAddUserModal();
    refreshData();
  } catch (err) {
    showError(err.message);
  }
});

async function deleteUser(userId, username) {
  if (!confirm(`Delete player "${username}" and all their progress? This cannot be undone.`)) return;
  try {
    await App.api('/api/admin/users/delete', { body: { user_id: userId } });
    App.toast(`Deleted ${username}`, 'success');
    loadUsersTable();
  } catch (e) {
    showError(e.message);
  }
}

// ---- Security -----------------------------------------------------------------
function checkPasswordStrength() {
  const pw = $('newAdminPassword').value;
  let strength = 0;
  if (pw.length >= 8) strength++;
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) strength++;
  if (/\d/.test(pw)) strength++;
  if (/[^a-zA-Z\d]/.test(pw)) strength++;
  $('passwordStrength').className = 'password-strength ' +
    (pw ? ['strength-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-very-strong'][strength] : '');
}

async function changeAdminPassword() {
  const current = $('currentPassword').value;
  const next = $('newAdminPassword').value;
  if (next !== $('confirmPassword').value) return showError('New passwords do not match.');
  if (next.length < 8) return showError('Password must be at least 8 characters long.');
  try {
    await App.api('/api/admin/password', { body: { current_password: current, new_password: next } });
    App.toast('Admin password updated', 'success');
    ['currentPassword', 'newAdminPassword', 'confirmPassword'].forEach((id) => ($(id).value = ''));
    checkPasswordStrength();
  } catch (e) {
    showError(e.message);
  }
}

async function loadSecurityLogs() {
  const box = $('securityLogs');
  try {
    const { logs } = await App.api('/api/admin/security-logs');
    box.innerHTML = logs.length ? logs.map((l) => {
      const dot = /failed|rejected/.test(l.event) ? 'dot-danger' : /success/.test(l.event) ? 'dot-success' : '';
      return `
        <div class="log-entry">
          <span class="dot ${dot}"></span>
          <div class="grow">
            <div><strong style="font-weight:500">${esc(l.event)}</strong> <span class="muted">· ${esc(l.user || 'unknown')}</span></div>
            <div class="subtle" style="font-size:13px">${[l.ip, l.detail].filter(Boolean).map(esc).join(' · ')}</div>
          </div>
          <time>${esc(when(l.timestamp).toLocaleString())}</time>
        </div>`;
    }).join('') : '<div class="empty">No security events logged yet.</div>';
  } catch (e) {
    box.innerHTML = '<div class="empty">Failed to load security logs.</div>';
  }
}

// ---- Analytics (computed from real runs/answers) -------------------------------
async function loadAnalytics() {
  try {
    const { analytics: a } = await App.api('/api/admin/analytics');
    $('avgGameDuration').textContent = a.avgGameDuration;
    $('successRate').textContent = a.successRate;
    $('popularSubject').textContent = a.popularSubject;
    $('dailyActiveUsers').textContent = a.dailyActiveUsers;
    $('retentionRate').textContent = a.completionRate;
    const q = a.hardestQuestions[0];
    $('avgSessionTime').textContent = q ? `${q.prompt} — ${q.pct}% correct` : 'Not enough answers yet';
  } catch (e) {
    showError('Failed to load analytics: ' + e.message);
  }
}

// ---- Data ---------------------------------------------------------------------
function refreshData() {
  (LOADERS[currentTab] || loadDashboardData)();
}

async function exportData() {
  try {
    const { data } = await App.api('/api/admin/export');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    link.download = `mazequest-export-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
  } catch (e) {
    showError('Export failed: ' + e.message);
  }
}

// ---- Modal --------------------------------------------------------------------
function openAddUserModal() {
  $('addUserModal').classList.add('show');
  $('newUsername').focus();
}

function closeAddUserModal() {
  $('addUserModal').classList.remove('show');
  $('addUserForm').reset();
}

$('addUserModal').addEventListener('click', (e) => { if (e.target.id === 'addUserModal') closeAddUserModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAddUserModal(); });

loadDashboardData();
