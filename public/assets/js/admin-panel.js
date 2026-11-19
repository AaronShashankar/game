// Admin panel. Access is enforced by the server (the page and every
// /api/admin/* endpoint require an admin session) — nothing here decides it.
const esc = (v) => App.escape(v);
const $ = (id) => document.getElementById(id);

function showError(message) {
  alert('❌ ' + message);
}

// ---- Tabs ---------------------------------------------------------------------
function switchTab(tabName) {
  document.querySelectorAll('.tab-content').forEach((t) => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
  $(tabName + '-tab').classList.add('active');
  if (window.event?.target) window.event.target.classList.add('active');
  ({ dashboard: loadDashboardData, users: loadUsersTable, security: loadSecurityLogs, analytics: loadAnalytics }[tabName] || (() => {}))();
}

// ---- Dashboard ----------------------------------------------------------------
async function loadDashboardData() {
  try {
    const { stats } = await App.api('/api/admin/stats');
    $('totalUsers').textContent = stats.totalUsers;
    $('totalGames').textContent = stats.totalGames;
    $('totalCoins').textContent = stats.totalCoins;
    $('activeUsers').textContent = stats.activeUsers;
    $('avgLevel').textContent = stats.avgLevel;
    $('totalXP').textContent = stats.totalXP;
    $('gatesOpened').textContent = stats.totalGates;
    $('correctAnswers').textContent = stats.totalCorrect;
    loadRecentActivity();
  } catch (e) {
    showError('Failed to load dashboard data: ' + e.message);
  }
}

async function loadRecentActivity() {
  const box = $('recentActivity');
  try {
    const { activity } = await App.api('/api/admin/activity');
    box.innerHTML = activity.length ? '' : '<div class="log-entry">No recent activity yet.</div>';
    activity.forEach((a) => {
      const div = document.createElement('div');
      div.className = `log-entry log-${a.type === 'info' ? 'warning' : 'success'}`;
      div.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;">
          <span>${esc(a.message)}</span><small style="color:#aaa;">${esc(new Date(a.time.replace(' ', 'T')).toLocaleString())}</small></div>`;
      box.appendChild(div);
    });
  } catch (e) {
    box.innerHTML = '<div class="log-entry log-error">Failed to load recent activity.</div>';
  }
}

// ---- Users --------------------------------------------------------------------
async function loadUsersTable() {
  const body = $('usersTableBody');
  body.innerHTML = '<tr><td colspan="11" class="loading"><div class="spinner"></div><p>Loading users...</p></td></tr>';
  try {
    const { users } = await App.api('/api/admin/users');
    if (!users.length) {
      body.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:20px;color:#aaa;">No players yet.</td></tr>';
      return;
    }
    body.innerHTML = '';
    users.forEach((u) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${esc(u.username)}</td><td>${u.level}</td><td>${u.coins}</td><td>${u.xp}</td><td>${u.streak}</td>
        <td>${u.total_games}</td><td>${u.powerups_used}</td><td>${u.correct_answers}</td><td>${u.wrong_answers}</td>
        <td>${esc(new Date(u.last_active.replace(' ', 'T')).toLocaleDateString())}</td>
        <td><div class="action-btns"><button class="btn btn-danger btn-small">Delete</button></div></td>`;
      // Username goes through a closure, not an inline onclick string.
      row.querySelector('button').addEventListener('click', () => deleteUser(u.id, u.username));
      body.appendChild(row);
    });
  } catch (e) {
    body.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:20px;color:#ff6b6b;">❌ ${esc(e.message)}</td></tr>`;
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
    alert('✅ User added');
    closeAddUserModal();
    loadUsersTable();
    loadDashboardData();
  } catch (err) {
    showError(err.message);
  }
});

async function deleteUser(userId, username) {
  if (!confirm(`Delete player "${username}" and all their progress? This cannot be undone.`)) return;
  try {
    await App.api('/api/admin/users/delete', { body: { user_id: userId } });
    loadUsersTable();
    loadDashboardData();
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
    ['strength-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-very-strong'][strength];
}

async function changeAdminPassword() {
  const current = $('currentPassword').value;
  const next = $('newAdminPassword').value;
  if (next !== $('confirmPassword').value) return showError('New passwords do not match.');
  if (next.length < 8) return showError('Password must be at least 8 characters long.');
  try {
    await App.api('/api/admin/password', { body: { current_password: current, new_password: next } });
    alert('✅ Admin password updated');
    ['currentPassword', 'newAdminPassword', 'confirmPassword'].forEach((id) => ($(id).value = ''));
  } catch (e) {
    showError(e.message);
  }
}

async function loadSecurityLogs() {
  const box = $('securityLogs');
  box.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading security logs...</p></div>';
  try {
    const { logs } = await App.api('/api/admin/security-logs');
    box.innerHTML = logs.length ? '' : '<div class="log-entry">No security events logged yet.</div>';
    logs.forEach((l) => {
      const cls = /failed|rejected/.test(l.event) ? 'log-error' : /success/.test(l.event) ? 'log-success' : 'log-warning';
      const div = document.createElement('div');
      div.className = 'log-entry ' + cls;
      div.innerHTML = `<div><strong>${esc(l.event)}</strong> — ${esc(l.user || 'unknown')}
          ${l.ip ? `<small>(${esc(l.ip)})</small>` : ''} ${l.detail ? `<small>${esc(l.detail)}</small>` : ''}</div>
        <small>${esc(new Date(l.timestamp.replace(' ', 'T')).toLocaleString())}</small>`;
      box.appendChild(div);
    });
  } catch (e) {
    box.innerHTML = '<div class="log-entry log-error">Failed to load security logs.</div>';
  }
}

// ---- Analytics (computed from real runs/answers) -------------------------------
async function loadAnalytics() {
  try {
    const { analytics: a } = await App.api('/api/admin/analytics');
    $('avgGameDuration').value = a.avgGameDuration;
    $('successRate').value = a.successRate;
    $('popularSubject').value = a.popularSubject;
    $('dailyActiveUsers').value = a.dailyActiveUsers;
    $('retentionRate').value = a.completionRate;
    $('avgSessionTime').value = a.hardestQuestions[0]
      ? `${a.hardestQuestions[0].prompt} (${a.hardestQuestions[0].pct}% correct)` : '—';
  } catch (e) {
    showError('Failed to load analytics: ' + e.message);
  }
}

// ---- System -------------------------------------------------------------------
function refreshData() {
  loadDashboardData();
}

// Backup = download a full JSON export.
const backupData = () => exportData();

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
  $('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
  $('addUserModal').style.display = 'none';
  $('addUserForm').reset();
}

window.addEventListener('click', (e) => {
  if (e.target === $('addUserModal')) closeAddUserModal();
});

document.addEventListener('DOMContentLoaded', loadDashboardData);
