<?php
/** @var string[] $subjects */
/** @var array $difficulties */
$title = 'Play';
$active = 'play';
require __DIR__ . '/../layouts/app-start.php';
$powers = [
    ['timeFreeze',   'snowflake', 'Time freeze'],
    ['hint',         'search',    'Hint'],
    ['skip',         'skip',      'Skip gate'],
    ['scoreDoubler', 'trend',     'Score doubler'],
];
?>

<div class="game">
  <div class="card board">
    <canvas id="mazeCanvas" width="600" height="600" tabindex="0" aria-label="Maze. Use the arrow keys to move."></canvas>
    <div class="board-foot">
      <span><span class="kbd">↑</span><span class="kbd">↓</span><span class="kbd">←</span><span class="kbd">→</span> or <span class="kbd">W</span><span class="kbd">A</span><span class="kbd">S</span><span class="kbd">D</span> to move</span>
      <span><span class="kbd">1</span>–<span class="kbd">4</span> power-ups</span>
      <span>Swipe on touch screens</span>
    </div>
  </div>

  <aside class="stack">
    <div class="card">
      <div class="row-between">
        <div style="min-width:0">
          <div class="card-title" id="runSubject">Loading…</div>
          <div class="card-sub" id="runDifficulty">&nbsp;</div>
        </div>
        <span class="chip" id="gateProgress">0 / 0 gates</span>
      </div>
      <div class="timer mt-16" id="timer">
        <span class="row" style="gap:8px"><?= icon('clock') ?><span style="font-size:13px">Time left</span></span>
        <span class="timer-value" id="timeLeft">—</span>
      </div>
      <div class="hud mt-16" id="hud">
        <div><div class="hud-label">Coins</div><div class="hud-value" id="coins">0</div></div>
        <div><div class="hud-label">Level</div><div class="hud-value" id="levelDisplay">1</div></div>
        <div><div class="hud-label">XP</div><div class="hud-value" id="xp">0</div></div>
        <div><div class="hud-label">Streak</div><div class="hud-value" id="streak">0</div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head" style="margin-bottom:12px">
        <div class="card-title">Power-ups</div>
        <button class="btn btn-ghost btn-sm" onclick="showShop()"><?= icon('plus', 'icon-sm') ?>Get more</button>
      </div>
      <div class="power-list">
        <?php foreach ($powers as $i => [$code, $ico, $label]): ?>
          <button class="power" id="<?= $code ?>Btn" onclick="usePower('<?= $code ?>')">
            <?= icon($ico) ?><span class="grow"><?= e($label) ?></span>
            <span class="kbd"><?= $i + 1 ?></span>
            <span class="power-count" id="<?= $code ?>Count">0</span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row">
      <button class="btn btn-secondary" style="flex:1" onclick="restartLevel()"><?= icon('rotate', 'icon-sm') ?>Restart maze</button>
      <a class="btn btn-ghost" href="<?= url('dashboard') ?>">Exit</a>
    </div>
  </aside>
</div>

<!-- Question -->
<div id="questionPopup" class="modal" role="dialog" aria-modal="true" aria-labelledby="questionText">
  <div class="modal-panel modal-wide">
    <div class="question-meta" id="questionMeta"></div>
    <h2 class="question-text" id="questionText"></h2>
    <div class="answers" id="options"></div>
    <p class="subtle mt-16" style="font-size:13px">Press <span class="kbd">1</span>–<span class="kbd">4</span> to answer. First try pays full, second pays half.</p>
  </div>
</div>

<!-- Maze complete -->
<div id="levelCompletePopup" class="modal" role="dialog" aria-modal="true" aria-labelledby="levelTitle">
  <div class="modal-panel" style="text-align:center">
    <div class="result-icon" style="margin:0 auto 18px"><?= icon('check') ?></div>
    <h2 class="modal-title" id="levelTitle">Maze complete</h2>
    <p class="modal-sub" id="levelSummary">&nbsp;</p>
    <div class="result-stats">
      <div><div class="hud-label">Coins</div><div class="hud-value" id="resultCoins">+0</div></div>
      <div><div class="hud-label">XP</div><div class="hud-value" id="resultXp">+0</div></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-primary btn-lg btn-block" onclick="nextLevel()">Next maze<?= icon('arrow-right', 'icon-sm') ?></button>
      <div class="modal-actions-row">
        <button class="btn btn-secondary" onclick="restartLevel()">Replay</button>
        <button class="btn btn-secondary" onclick="goToDashboard()">Dashboard</button>
      </div>
    </div>
  </div>
</div>

<!-- In-game shop -->
<div id="shopPopup" class="modal" role="dialog" aria-modal="true" aria-labelledby="shopTitle">
  <div class="modal-panel modal-wide">
    <button class="btn btn-ghost btn-icon modal-close" onclick="hideShop()" aria-label="Close"><?= icon('x') ?></button>
    <h2 class="modal-title" id="shopTitle">Get power-ups</h2>
    <p class="modal-sub">Balance: <strong class="num" id="shopCoins">0</strong> coins</p>
    <div class="list mt-16" id="shopList"><div class="empty">Loading…</div></div>
  </div>
</div>

<script>
  // Display settings and subjects from the server (GameRules / questions table).
  window.GameConfig = <?= json_encode(['difficulties' => $difficulties, 'subjects' => $subjects], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= page_script('game') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
