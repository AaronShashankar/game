<?php
use App\Services\GameRules;

/** @var array $difficulties */
$title = 'Dashboard';
$active = 'dashboard';
$head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>';
require __DIR__ . '/../layouts/app-start.php';
$diffCopy = ['Easy' => 'Small maze, no timer', 'Medium' => 'Bigger maze, against the clock', 'Hard' => 'Large maze in fog of war'];
?>

<div class="page-head">
  <div>
    <h1>Hi, <span id="profileUsername"><?= e($me['username'] ?? '') ?></span></h1>
    <p>Here's where you stand today.</p>
  </div>
  <a href="<?= url('choose-subject') ?>" class="btn btn-primary"><?= icon('play') ?>New game</a>
</div>

<div class="grid grid-4">
  <div class="card">
    <div class="stat-label"><?= icon('coins') ?>Coins</div>
    <div class="stat-value" id="coins">—</div>
    <div class="stat-foot">Spend them in the shop</div>
  </div>
  <div class="card">
    <div class="stat-label"><?= icon('star') ?>Level</div>
    <div class="stat-value" id="level">—</div>
    <div class="progress mt-16"><span id="xpFill"></span></div>
    <div class="stat-foot"><span id="xp">—</span> XP to next level</div>
  </div>
  <div class="card">
    <div class="stat-label"><?= icon('flame') ?>Streak</div>
    <div class="stat-value"><span id="streak">—</span></div>
    <div class="stat-foot">days in a row</div>
  </div>
  <div class="card">
    <div class="stat-label"><?= icon('target') ?>Accuracy</div>
    <div class="stat-value" id="accuracy">—</div>
    <div class="stat-foot"><span id="gamesPlayed">0</span> games played</div>
  </div>
</div>

<div class="grid grid-3 mt-16">
  <div class="card span-2">
    <div class="card-head">
      <div>
        <div class="card-title">Start a game</div>
        <div class="card-sub">Choose a difficulty, then a subject.</div>
      </div>
    </div>
    <div class="option-grid">
      <?php foreach ($difficulties as $name => $d): ?>
        <a class="option" href="<?= url('choose-subject') ?>?difficulty=<?= strtolower($name) ?>">
          <span class="option-title"><?= e($name) ?> <?= icon('arrow-right', 'icon-sm subtle') ?></span>
          <span class="option-desc"><?= e($diffCopy[$name] ?? '') ?></span>
          <span class="option-meta">
            <span class="chip"><?= $d['gates'] ?> gates</span>
            <span class="chip"><?= $d['time'] ? floor($d['time'] / 60) . ' min' : 'No timer' ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div class="card-title">Daily rewards</div></div>
    <div class="reward-row">
      <div class="icon-box"><?= icon('calendar') ?></div>
      <div class="grow">
        <div class="list-title">Daily bonus</div>
        <div class="list-meta" id="bonusMeta">Keep your streak going</div>
      </div>
      <button class="btn btn-secondary btn-sm" id="bonusBtn" onclick="claimBonus()">Claim</button>
    </div>
    <div class="reward-row">
      <div class="icon-box"><?= icon('rotate') ?></div>
      <div class="grow">
        <div class="list-title">Lucky spin</div>
        <div class="list-meta"><span id="spinsLeftMeta">—</span> left today · <?= GameRules::SPIN_COST ?> coins</div>
      </div>
      <button class="btn btn-secondary btn-sm" id="spinBtn" onclick="openSpinWheel()">Spin</button>
    </div>
  </div>
</div>

<div class="grid grid-3 mt-16">
  <div class="card span-2">
    <div class="card-head">
      <div>
        <div class="card-title">XP this week</div>
        <div class="card-sub">Earned per day over the last 7 days</div>
      </div>
    </div>
    <div class="chart-box"><canvas id="progressChart"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head"><div class="card-title">Achievements</div></div>
    <div class="badge-list" id="badges"><span class="subtle" style="font-size:14px">—</span></div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-head">
    <div class="card-title">Top players</div>
    <div class="tabs" role="tablist">
      <button class="tab lb-btn is-active" id="xpBtn" onclick="loadLeaderboard('xp')">XP</button>
      <button class="tab lb-btn" id="coinsBtn" onclick="loadLeaderboard('coins')">Coins</button>
      <button class="tab lb-btn" id="levelBtn" onclick="loadLeaderboard('level')">Level</button>
      <button class="tab lb-btn" id="streakBtn" onclick="loadLeaderboard('streak')">Streak</button>
    </div>
  </div>
  <div class="list" id="leaderboardContent"><div class="empty">Loading…</div></div>
</div>

<!-- Spin wheel -->
<div id="spinModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="spinTitle">
  <div class="modal-panel" style="text-align:center">
    <button class="btn btn-ghost btn-icon modal-close" onclick="closeSpinWheel()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="spinTitle">Lucky spin</h2>
    <p class="modal-sub"><?= GameRules::SPIN_COST ?> coins per spin · <span id="spinsLeft">—</span> left today</p>
    <div class="wheel-wrap">
      <div class="wheel-pointer"></div>
      <div class="wheel" id="wheel">
        <?php foreach (GameRules::SPIN_SEGMENTS as $i => $seg): ?>
          <div class="wheel-item" style="transform: rotate(<?= 30 + 60 * $i ?>deg)"><?= e($seg['label']) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="wheel-hub"><?= icon('gift', 'icon-sm') ?></div>
    </div>
    <div class="spin-result" id="spinResult">&nbsp;</div>
    <button class="btn btn-primary btn-lg btn-block mt-16" id="spinWheelBtn" onclick="spinWheel()">Spin</button>
  </div>
</div>

<?= page_script('dashboard') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
