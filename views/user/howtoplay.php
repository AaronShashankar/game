<?php
use App\Services\GameRules as R;

/** @var array $difficulties */
$title = 'How to play';
$active = 'howtoplay';
$signedIn = current_player() !== null;
require __DIR__ . '/../layouts/' . ($signedIn ? 'app-start.php' : 'header.php');
$fmt = fn (?int $s) => $s ? floor($s / 60) . ':' . str_pad((string) ($s % 60), 2, '0') : 'None';
?>

<?php if (!$signedIn): ?><div class="container page-hero" style="padding-bottom:40px"><?php endif; ?>
<div class="page-head">
  <div>
    <h1>How to play</h1>
    <p>Reach the exit. Every gate on the way asks one question.</p>
  </div>
  <a class="btn btn-primary" href="<?= url($signedIn ? 'choose-subject' : 'register') ?>"><?= icon('play') ?><?= $signedIn ? 'Start a game' : 'Create an account' ?></a>
</div>
<?php if (!$signedIn): ?></div><div class="container"><?php endif; ?>

<div class="steps">
  <div class="step"><span class="step-no">01</span><h3>Pick a level</h3><p>Choose a difficulty and a subject — or mixed mode for everything.</p></div>
  <div class="step"><span class="step-no">02</span><h3>Walk the maze</h3><p>Move with the arrow keys or WASD. On a phone, swipe on the maze.</p></div>
  <div class="step"><span class="step-no">03</span><h3>Open the gates</h3><p>Walking into a numbered gate asks a question. Answer it to pass.</p></div>
  <div class="step"><span class="step-no">04</span><h3>Reach the exit</h3><p>The dark tile in the bottom-right corner. Faster runs earn a time bonus.</p></div>
</div>

<div class="grid grid-2 mt-16">
  <div class="card">
    <div class="card-head"><div class="card-title">Rewards</div></div>
    <dl class="kv">
      <div><dt>Correct answer, first try</dt><dd><?= R::GATE_COINS ?> coins · <?= R::GATE_XP ?> XP</dd></div>
      <div><dt>Correct answer, second try</dt><dd>Half</dd></div>
      <div><dt>Third try or later</dt><dd>Nothing</dd></div>
      <div><dt>Combo (answers in a row)</dt><dd>+<?= R::COMBO_STEP ?> each, up to +<?= R::COMBO_MAX_BONUS ?></dd></div>
      <div><dt>Finishing the maze</dt><dd><?= R::COMPLETE_COINS ?> coins · <?= R::COMPLETE_XP ?> XP, × difficulty</dd></div>
      <div><dt>Time bonus</dt><dd>1 coin per <?= R::TIME_BONUS_DIVISOR ?>s left</dd></div>
      <div><dt>Daily check-in</dt><dd><?= R::CHECKIN_BASE ?> coins + <?= R::CHECKIN_PER_STREAK_DAY ?>/streak day</dd></div>
    </dl>
  </div>
  <div class="card">
    <div class="card-head"><div class="card-title">Power-ups</div></div>
    <div class="list">
      <div class="list-row"><div class="icon-box"><?= icon('snowflake') ?></div><div class="grow"><div class="list-title">Time freeze</div><div class="list-meta">Stops the clock for <?= R::FREEZE_SECONDS ?> seconds</div></div><span class="kbd">1</span></div>
      <div class="list-row"><div class="icon-box"><?= icon('search') ?></div><div class="grow"><div class="list-title">Hint</div><div class="list-meta">Highlights the nearest closed gate</div></div><span class="kbd">2</span></div>
      <div class="list-row"><div class="icon-box"><?= icon('skip') ?></div><div class="grow"><div class="list-title">Skip gate</div><div class="list-meta">Opens the nearest gate, without its reward</div></div><span class="kbd">3</span></div>
      <div class="list-row"><div class="icon-box"><?= icon('trend') ?></div><div class="grow"><div class="list-title">Score doubler</div><div class="list-meta">Doubles your next correct answer</div></div><span class="kbd">4</span></div>
    </div>
  </div>
</div>

<div class="card card-flush mt-16">
  <div style="padding:20px 24px 4px"><div class="card-title">Difficulty levels</div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Level</th><th class="num">Maze</th><th class="num">Gates</th><th class="num">Time limit</th><th class="num">Wrong-answer penalty</th><th>Fog of war</th><th class="num">Rewards</th></tr></thead>
      <tbody>
        <?php foreach ($difficulties as $name => $d): ?>
          <tr>
            <td><strong style="font-weight:600"><?= e($name) ?></strong></td>
            <td class="num"><?= $d['size'] ?> × <?= $d['size'] ?></td>
            <td class="num"><?= $d['gates'] ?></td>
            <td class="num"><?= $fmt($d['time']) ?></td>
            <td class="num"><?= $d['penalty'] ? $d['penalty'] . ' coins' : '—' ?></td>
            <td><?= $d['fog'] ? 'Yes' : 'No' ?></td>
            <td class="num">×<?= rtrim(rtrim(number_format($d['mult'], 1), '0'), '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="subtle mt-16" style="font-size:13px">Three wrong answers in a row send you back to the start. When the timer runs out you go back to the start with a fresh clock. Answers sent faster than a human could read the question are rejected.</p>

<?php if (!$signedIn): ?></div><?php endif; ?>
<?php require __DIR__ . '/../layouts/' . ($signedIn ? 'app-end.php' : 'footer.php'); ?>
