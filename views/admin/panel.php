<?php
use App\Services\GameRules;
use App\Services\QuestionService;
use App\Services\SubjectService;

$title = 'Admin';
require __DIR__ . '/../layouts/document-start.php';
$tabs = [
    'dashboard' => ['Overview',  'grid'],
    'users'     => ['Players',   'users'],
    'subjects'  => ['Subjects',  'folder'],
    'questions' => ['Questions', 'book'],
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

      <!-- Subjects -->
      <section id="subjects-tab" class="tab-content">
        <div class="card card-flush">
          <div class="toolbar">
            <p class="grow subtle" style="font-size:13px">New subjects start hidden, so you can write their questions first.
              Show a subject to players once it has at least <?= GameRules::minSubjectQuestions() ?> active questions.</p>
            <button class="btn btn-primary btn-sm" onclick="openSubjectModal()"><?= icon('plus', 'icon-sm') ?>Add subject</button>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>Subject</th><th class="num">Questions</th><th>By difficulty</th><th>Players</th><th></th></tr>
              </thead>
              <tbody id="subjectRows"><tr><td colspan="5" class="empty"><div class="spinner"></div>Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Questions -->
      <section id="questions-tab" class="tab-content">
        <div class="card card-flush">
          <div class="toolbar">
            <input class="input grow" type="search" id="qSearch" name="question-search" autocomplete="off" placeholder="Search question text" aria-label="Search questions">
            <select class="input" id="qSubject" aria-label="Subject"><option value="">All subjects</option></select>
            <select class="input" id="qDifficulty" aria-label="Difficulty">
              <option value="">Any difficulty</option><option>Easy</option><option>Medium</option><option>Hard</option>
            </select>
            <select class="input" id="qStatus" aria-label="Status">
              <option value="active">Active</option><option value="archived">Archived</option><option value="">All</option>
            </select>
            <button class="btn btn-secondary btn-sm" onclick="openImportModal()"><?= icon('upload', 'icon-sm') ?>Import CSV</button>
            <button class="btn btn-secondary btn-sm" onclick="openQuestionModal()"><?= icon('plus', 'icon-sm') ?>Add question</button>
          </div>
          <div class="toolbar bulk-bar" id="qBulkBar" hidden>
            <span class="grow" id="qSelectedCount" style="font-size:14px; font-weight:500"></span>
            <button class="btn btn-ghost btn-sm" onclick="clearQuestionSelection()">Clear selection</button>
            <button class="btn btn-danger btn-sm" onclick="deleteSelectedQuestions()"><?= icon('trash', 'icon-sm') ?>Delete selected</button>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th class="check-cell"><input type="checkbox" id="qSelectAll" aria-label="Select all questions shown"></th>
                  <th>Question</th><th>Subject</th><th>Difficulty</th><th class="num">Answered</th><th class="num">Correct</th><th></th></tr>
              </thead>
              <tbody id="questionRows"><tr><td colspan="7" class="empty"><div class="spinner"></div>Loading…</td></tr></tbody>
            </table>
          </div>
          <div class="toolbar" style="border-top:1px solid var(--border); border-bottom:0"><span class="subtle" style="font-size:13px" id="qCount"></span></div>
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

<!-- Question editor -->
<div id="questionModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="questionModalTitle">
  <div class="modal-panel modal-wide">
    <button class="btn btn-ghost btn-icon modal-close" onclick="closeQuestionModal()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="questionModalTitle">Add question</h2>
    <p class="modal-sub">Players see the explanation after they answer.</p>
    <form id="questionForm" class="form mt-24">
      <input type="hidden" id="qId">
      <div class="form-row">
        <div class="field">
          <div class="row-between"><label for="qFormSubject">Subject</label>
            <button type="button" class="link link-btn" style="font-size:13px" onclick="closeQuestionModal(); switchTab('subjects'); openSubjectModal()">New subject</button></div>
          <select class="input" id="qFormSubject" required></select></div>
        <div class="field"><label for="qFormDifficulty">Difficulty</label>
          <select class="input" id="qFormDifficulty"><option>Easy</option><option selected>Medium</option><option>Hard</option></select></div>
      </div>
      <div class="field"><label for="qFormPrompt">Question</label><textarea class="input" id="qFormPrompt" required maxlength="500" rows="2"></textarea></div>
      <div class="field">
        <label>Answers <span class="form-hint">(2–4; select the correct one)</span></label>
        <div class="option-inputs">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <label class="option-input"><input type="radio" name="qCorrect" value="<?= $i ?>"<?= $i === 0 ? ' checked' : '' ?> aria-label="Option <?= $i + 1 ?> is correct">
              <input class="input" id="qOpt<?= $i ?>" maxlength="200" placeholder="Option <?= $i + 1 ?><?= $i >= 2 ? ' (optional)' : '' ?>"></label>
          <?php endfor; ?>
        </div>
      </div>
      <div class="field"><label for="qFormExplanation">Explanation</label><textarea class="input" id="qFormExplanation" maxlength="500" rows="2" placeholder="Why the answer is right, in a sentence or two"></textarea></div>
      <label class="row" style="gap:8px; font-size:14px"><input type="checkbox" id="qFormActive" checked> Active (can be asked in games)</label>
      <div class="form-actions mt-8">
        <button type="button" class="btn btn-ghost" onclick="closeQuestionModal()">Cancel</button>
        <button type="submit" class="btn btn-secondary" data-another="1" id="qSaveAnother">Save and add another</button>
        <button type="submit" class="btn btn-primary">Save question</button>
      </div>
    </form>
  </div>
</div>

<!-- Subject editor -->
<div id="subjectModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="subjectModalTitle">
  <div class="modal-panel">
    <button class="btn btn-ghost btn-icon modal-close" onclick="closeSubjectModal()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="subjectModalTitle">Add subject</h2>
    <p class="modal-sub" id="subjectModalSub"></p>
    <form id="subjectForm" class="form mt-24">
      <input type="hidden" id="sId">
      <div class="field"><label for="sName">Name</label><input class="input" id="sName" required maxlength="100" placeholder="e.g. Computer Software" autocomplete="off"></div>
      <div class="field"><label for="sDescription">Description <span class="form-hint">(optional, shown on the subject picker)</span></label>
        <input class="input" id="sDescription" maxlength="200" placeholder="e.g. Operating systems, apps and how software is built" autocomplete="off"></div>
      <div class="field">
        <span class="label" id="sIconLabel">Icon</span>
        <div class="icon-picker" role="radiogroup" aria-labelledby="sIconLabel">
          <?php foreach (SubjectService::ICONS as $i => $ico): ?>
            <label class="icon-pick"><input type="radio" name="sIcon" value="<?= e($ico) ?>"<?= $i === 0 ? ' checked' : '' ?> aria-label="<?= e($ico) ?>"><span><?= icon($ico) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-actions mt-8">
        <button type="button" class="btn btn-ghost" onclick="closeSubjectModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save subject</button>
      </div>
    </form>
  </div>
</div>

<!-- CSV import -->
<div id="importModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="importTitle">
  <div class="modal-panel modal-wide">
    <button class="btn btn-ghost btn-icon modal-close" onclick="closeImportModal()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="importTitle">Import questions</h2>
    <p class="modal-sub">One question per row. Columns: <code><?= e(implode(',', QuestionService::CSV_COLUMNS)) ?></code>.
      <code>subject</code> must already exist on the Subjects tab.
      <code>answer</code> is the number of the correct option (1–4); option3 and option4 can be empty.
      Every row is checked first: if any row has a problem, nothing is imported. Questions already in the bank are skipped.</p>
    <div class="form mt-24">
      <div class="field"><label for="csvFile">CSV file</label><input class="input" type="file" id="csvFile" accept=".csv,text/csv" style="padding-top:8px"></div>
      <div class="import-errors" id="importErrors" hidden></div>
      <div class="form-actions">
        <button type="button" class="btn btn-ghost" onclick="downloadTemplate()"><?= icon('download', 'icon-sm') ?>Template</button>
        <button type="button" class="btn btn-primary" id="importBtn" onclick="importCsv()">Import</button>
      </div>
    </div>
  </div>
</div>

<script>window.CsvColumns = <?= json_encode(QuestionService::CSV_COLUMNS) ?>;</script>
<?= page_script('admin-panel') ?>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>
