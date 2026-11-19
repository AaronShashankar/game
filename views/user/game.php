<?php
/** @var string[] $subjects */
/** @var array $difficulties */
use App\Services\GameRules;

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
    <div class="dpad" id="dpad" aria-label="Move">
      <button type="button" class="up" data-dir="up" aria-label="Up"><?= icon('chevron-up') ?></button>
      <button type="button" class="left" data-dir="left" aria-label="Left"><?= icon('chevron-left') ?></button>
      <button type="button" class="down" data-dir="down" aria-label="Down"><?= icon('chevron-down') ?></button>
      <button type="button" class="right" data-dir="right" aria-label="Right"><?= icon('chevron-right') ?></button>
    </div>
    <div class="board-foot">
      <span class="keys-hint"><span class="kbd">↑</span><span class="kbd">↓</span><span class="kbd">←</span><span class="kbd">→</span> or <span class="kbd">W</span><span class="kbd">A</span><span class="kbd">S</span><span class="kbd">D</span> to move</span>
      <span class="keys-hint"><span class="kbd">1</span>–<span class="kbd">4</span> power-ups</span>
      <span>Coins in dead ends pay <?= GameRules::PICKUP_COINS ?> each when you finish</span>
    </div>
  </div>

  <aside class="stack">
    <div class="card">
      <div class="row-between">
        <div style="min-width:0">
          <div class="card-title" id="runSubject">Loading…</div>
          <div class="card-sub" id="runDifficulty">&nbsp;</div>
        </div>
        <button class="btn btn-ghost btn-icon" id="soundBtn" onclick="toggleSound()" aria-label="Mute sound" title="Sound on"></button>
      </div>
      <div class="row mt-16" style="gap:6px; flex-wrap:wrap">
        <span class="chip" id="gateProgress">0 / 0 gates</span>
        <span class="chip" id="pickupProgress" hidden><?= icon('coins') ?><span>0 / 0</span></span>
        <span class="chip chip-accent" id="modeChip" hidden></span>
      </div>
      <div class="callout callout-accent" id="opponentBox" hidden>
        <?= icon('swords') ?><div class="grow" id="opponentText"></div>
      </div>
      <div class="timer mt-16" id="timer">
        <span class="row" style="gap:8px"><?= icon('clock') ?><span style="font-size:13px">Time left</span></span>
        <span class="timer-value" id="timeLeft">—</span>
      </div>
      <div class="hud mt-16" id="hud">
        <div><div class="hud-label">Coins</div><div class="hud-value" id="coins">0</div></div>
        <div><div class="hud-label">Level</div><div class="hud-value" id="levelDisplay">1</div></div>
        <div><div class="hud-label">XP</div><div class="hud-value" id="xp">0</div></div>
        <div><div class="hud-label">Combo</div><div class="hud-value" id="combo">0</div></div>
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
            <span class="kbd keys-hint"><?= $i + 1 ?></span>
            <span class="power-count" id="<?= $code ?>Count">0</span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row">
      <button class="btn btn-secondary" style="flex:1" id="restartBtn" onclick="restartLevel()"><?= icon('rotate', 'icon-sm') ?>Restart maze</button>
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
    <div class="feedback" id="feedback" hidden aria-live="polite">
      <div class="feedback-title" id="feedbackTitle"></div>
      <p id="feedbackText"></p>
    </div>
    <div class="question-foot">
      <p class="subtle" style="font-size:13px" id="questionHint">Press <span class="kbd">1</span>–<span class="kbd">4</span> to answer. First try pays full, second pays half.</p>
      <button class="btn btn-primary" id="continueBtn" hidden onclick="closeQuestion()">Continue <span class="kbd" style="margin-left:4px">↵</span></button>
    </div>
  </div>
</div>

<!-- Maze complete -->
<div id="levelCompletePopup" class="modal" role="dialog" aria-modal="true" aria-labelledby="levelTitle">
  <div class="modal-panel modal-wide" style="text-align:center">
    <div class="result-icon" style="margin:0 auto 18px" id="resultIcon"><?= icon('check') ?></div>
    <h2 class="modal-title" id="levelTitle">Maze complete</h2>
    <p class="modal-sub" id="levelSummary">&nbsp;</p>

    <div class="result-stats result-stats-4">
      <div><div class="hud-label">Time</div><div class="hud-value" id="sumTime">—</div></div>
      <div><div class="hud-label">Accuracy</div><div class="hud-value" id="sumAccuracy">—</div></div>
      <div><div class="hud-label">Best combo</div><div class="hud-value" id="sumCombo">—</div></div>
      <div><div class="hud-label">Coins found</div><div class="hud-value" id="sumPickups">—</div></div>
    </div>

    <div class="reward-lines" id="rewardLines"></div>
    <div class="callout" id="resultCallout" hidden></div>

    <div class="recap" id="recap" hidden>
      <h3>Worth another look</h3>
      <div id="recapList"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-primary btn-lg btn-block" onclick="nextLevel()">Next maze<?= icon('arrow-right', 'icon-sm') ?></button>
      <div class="modal-actions-row">
        <button class="btn btn-secondary" id="replayBtn" onclick="restartLevel()">Replay</button>
        <button class="btn btn-secondary" id="shareBtn" onclick="shareChallenge()"><?= icon('share', 'icon-sm') ?>Challenge a friend</button>
      </div>
      <button class="btn btn-ghost" onclick="goToDashboard()">Back to dashboard</button>
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
  window.GameConfig = <?= json_encode([
      'difficulties' => $difficulties, 'subjects' => $subjects,
      'pickupCoins' => GameRules::PICKUP_COINS, 'dailyDifficulty' => GameRules::DAILY_DIFFICULTY,
  ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset('js/lib/sound.js')) ?>"></script>
<?= page_script('game') ?>
<?php require __DIR__ . '/../layouts/app-end.php'; ?>
