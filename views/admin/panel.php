<?php
$title = 'Admin';
require __DIR__ . '/../layouts/document-start.php';
$tabs = [
    'dashboard' => ['Overview',  'grid'],
    'users'     => ['Players',   'users'],
    'analytics' => ['Analytics', 'chart'],
    'security'  => ['Security',  'shield'],
    'system'    => ['Data',      'server'],
];
?>
<div class="shell">
  <aside class="sidebar">
    <div class="row" style="padding:4px 10px; gap:8px"><?= brand() ?><span class="chip">Admin</span></div>
    <nav class="side-nav" aria-label="Admin">
      <?php foreach ($tabs as $key => [$label, $ico]): ?>
        <button type="button" class="side-link tab-btn<?= $key === 'dashboard' ? ' is-active' : '' ?>" data-tab="<?= $key ?>"><?= icon($ico) ?><span><?= e($label) ?></span></button>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('') ?>" class="side-link"><?= icon('home') ?><span>View site</span></a>
      <button type="button" class="side-link" onclick="logout()"><?= icon('logout') ?><span>Log out</span></button>
    </div>
  </aside>

  <div class="shell-main">
    <header class="topbar">
      <div class="topbar-title" id="tabTitle">Overview</div>
      <div class="topbar-actions">
        <button class="btn btn-ghost btn-sm" onclick="refreshData()"><?= icon('refresh', 'icon-sm') ?><span class="hide-sm">Refresh</span></button>
        <button class="btn btn-primary btn-sm" onclick="openAddUserModal()"><?= icon('plus', 'icon-sm') ?><span class="hide-sm">Add player</span></button>
        <?= theme_toggle() ?>
      </div>
    </header>

    <main class="page">
      <!-- Overview -->
      <section id="dashboard-tab" class="tab-content active">
        <div class="grid grid-4">
          <div class="card"><div class="stat-label"><?= icon('users') ?>Players</div><div class="stat-value" id="totalUsers">—</div><div class="stat-foot"><span id="activeUsers">—</span> active today</div></div>
          <div class="card"><div class="stat-label"><?= icon('play') ?>Games played</div><div class="stat-value" id="totalGames">—</div><div class="stat-foot"><span id="gatesOpened">—</span> gates opened</div></div>
          <div class="card"><div class="stat-label"><?= icon('check') ?>Correct answers</div><div class="stat-value" id="correctAnswers">—</div><div class="stat-foot">across all runs</div></div>
          <div class="card"><div class="stat-label"><?= icon('star') ?>Average level</div><div class="stat-value" id="avgLevel">—</div><div class="stat-foot"><span id="totalXP">—</span> XP · <span id="totalCoins">—</span> coins held</div></div>
        </div>
        <div class="card mt-16">
          <div class="card-head"><div><div class="card-title">Recent activity</div><div class="card-sub">Latest games and sign-ups</div></div></div>
          <div class="log" id="recentActivity"><div class="empty"><div class="spinner"></div>Loading…</div></div>
        </div>
      </section>

      <!-- Players -->
      <section id="users-tab" class="tab-content">
        <div class="card card-flush">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Player</th><th class="num">Level</th><th class="num">Coins</th><th class="num">XP</th><th class="num">Streak</th>
                  <th class="num">Games</th><th class="num">Power-ups</th><th class="num">Correct</th><th class="num">Wrong</th><th>Last active</th><th></th>
                </tr>
              </thead>
              <tbody id="usersTableBody"><tr><td colspan="11" class="empty"><div class="spinner"></div>Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Analytics -->
      <section id="analytics-tab" class="tab-content">
        <div class="grid grid-3">
          <div class="card"><div class="stat-label"><?= icon('clock') ?>Average game</div><div class="stat-value" style="font-size:22px" id="avgGameDuration">—</div></div>
          <div class="card"><div class="stat-label"><?= icon('target') ?>Answer success rate</div><div class="stat-value" style="font-size:22px" id="successRate">—</div></div>
          <div class="card"><div class="stat-label"><?= icon('flag') ?>Run completion rate</div><div class="stat-value" style="font-size:22px" id="retentionRate">—</div></div>
          <div class="card"><div class="stat-label"><?= icon('book') ?>Most played subject</div><div class="stat-value" style="font-size:22px" id="popularSubject">—</div></div>
          <div class="card"><div class="stat-label"><?= icon('activity') ?>Active players today</div><div class="stat-value" style="font-size:22px" id="dailyActiveUsers">—</div></div>
          <div class="card"><div class="stat-label"><?= icon('bulb') ?>Hardest question</div><p class="mt-8" style="font-weight:500" id="avgSessionTime">—</p></div>
        </div>
      </section>

      <!-- Security -->
      <section id="security-tab" class="tab-content">
        <div class="grid grid-2">
          <div class="card">
            <div class="card-head"><div><div class="card-title">Change admin password</div><div class="card-sub">At least 8 characters.</div></div></div>
            <div class="form">
              <div class="field"><label for="currentPassword">Current password</label><input class="input" type="password" id="currentPassword" autocomplete="current-password"></div>
              <div class="field">
                <label for="newAdminPassword">New password</label>
                <input class="input" type="password" id="newAdminPassword" autocomplete="new-password" oninput="checkPasswordStrength()">
                <div class="password-strength" id="passwordStrength"></div>
              </div>
              <div class="field"><label for="confirmPassword">Confirm new password</label><input class="input" type="password" id="confirmPassword" autocomplete="new-password"></div>
              <div class="form-actions"><button class="btn btn-primary" onclick="changeAdminPassword()">Update password</button></div>
            </div>
          </div>
          <div class="card">
            <div class="card-head"><div class="card-title">What's enforced</div></div>
            <div class="list">
              <div class="list-row"><div class="icon-box"><?= icon('lock') ?></div><div class="grow"><div class="list-title">Login rate limit</div><div class="list-meta">10 failed attempts per IP per 15 minutes</div></div></div>
              <div class="list-row"><div class="icon-box"><?= icon('shield') ?></div><div class="grow"><div class="list-title">Server-checked games</div><div class="list-meta">Rejected answers and runs are logged below</div></div></div>
              <div class="list-row"><div class="icon-box"><?= icon('x') ?></div><div class="grow"><div class="list-title">Not implemented</div><div class="list-meta">Two-factor sign-in and custom session timeouts</div></div></div>
            </div>
          </div>
        </div>
        <div class="card mt-16">
          <div class="card-head"><div><div class="card-title">Security log</div><div class="card-sub">Logins and rejected game events</div></div></div>
          <div class="log" id="securityLogs" style="max-height:420px; overflow-y:auto"><div class="empty"><div class="spinner"></div>Loading…</div></div>
        </div>
      </section>

      <!-- Data -->
      <section id="system-tab" class="tab-content">
        <div class="card" style="max-width:640px">
          <div class="row" style="align-items:flex-start; gap:16px">
            <div class="icon-box"><?= icon('download') ?></div>
            <div class="grow">
              <div class="card-title">Export data</div>
              <p class="muted mt-8" style="font-size:14px">Download players, runs, questions and shop items as JSON, as a backup or for your own analysis. Passwords are not included.</p>
              <button class="btn btn-secondary mt-16" onclick="exportData()"><?= icon('download', 'icon-sm') ?>Download JSON</button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- Add player -->
<div id="addUserModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="addUserTitle">
  <div class="modal-panel">
    <button class="btn btn-ghost btn-icon modal-close" onclick="closeAddUserModal()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="addUserTitle">Add player</h2>
    <p class="modal-sub">Starting coins are recorded in the ledger as an admin grant.</p>
    <form id="addUserForm" class="form mt-24">
      <div class="field"><label for="newUsername">Username</label><input class="input" type="text" id="newUsername" required></div>
      <div class="field"><label for="newEmail">Email</label><input class="input" type="email" id="newEmail" required></div>
      <div class="field"><label for="newUserPassword">Password</label><input class="input" type="password" id="newUserPassword" required minlength="6"></div>
      <div class="form-row">
        <div class="field"><label for="initialCoins">Starting coins</label><input class="input" type="number" id="initialCoins" value="100" min="0"></div>
        <div class="field"><label for="initialLevel">Starting level</label><input class="input" type="number" id="initialLevel" value="1" min="1" max="10"></div>
      </div>
      <div class="form-actions mt-8">
        <button type="button" class="btn btn-ghost" onclick="closeAddUserModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Add player</button>
      </div>
    </form>
  </div>
</div>

<?= page_script('admin-panel') ?>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>
