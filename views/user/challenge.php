<?php
// Public landing page for a "challenge a friend" link: /challenge?code=XXXXXXXX
/** @var array|null $challenge */
$title = $challenge ? $challenge['username'] . ' challenges you' : 'Challenge';
require __DIR__ . '/../layouts/document-start.php';
$me = current_player();
$fmt = fn (int $s) => intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
$subject = $challenge && $challenge['subject'] === App\Services\GameRules::MIXED_SUBJECT ? 'mixed-subject' : ($challenge['subject'] ?? '');
$next = $challenge ? '/game?challenge=' . $challenge['code'] : '';
?>
<div class="auth-top">
  <a href="<?= url('') ?>"><?= brand() ?></a>
  <?= theme_toggle() ?>
</div>

<main class="auth">
  <div class="auth-card" style="text-align:center">
    <?php if ($challenge === null): ?>
      <div class="icon-box" style="margin:0 auto 18px"><?= icon('flag') ?></div>
      <h1>Challenge not found</h1>
      <p class="auth-sub">This challenge link is not valid. Check that you copied all of it, or ask your friend to send it again.</p>
      <a class="btn btn-primary btn-lg btn-block mt-24" href="<?= url($me ? 'dashboard' : '') ?>"><?= $me ? 'Go to your dashboard' : 'Discover MazeQuest' ?></a>
    <?php else: ?>
      <div class="icon-box icon-box-accent" style="margin:0 auto 18px"><?= icon('swords') ?></div>
      <h1><?= e($challenge['username']) ?> challenges you</h1>
      <p class="auth-sub">Finish their <?= e(strtolower($challenge['difficulty'])) ?> <?= e($subject) ?> maze faster than
        <strong style="color:var(--text)"><?= $fmt($challenge['duration_seconds']) ?></strong>. Same maze, same questions.</p>

      <div class="result-stats" style="grid-template-columns:repeat(3,1fr)">
        <div><div class="hud-label">Difficulty</div><div class="hud-value" style="font-size:16px"><?= e($challenge['difficulty']) ?></div></div>
        <div><div class="hud-label">Gates</div><div class="hud-value" style="font-size:16px"><?= (int) $challenge['gate_count'] ?></div></div>
        <div><div class="hud-label">Time to beat</div><div class="hud-value" style="font-size:16px"><?= $fmt($challenge['duration_seconds']) ?></div></div>
      </div>

      <?php if ($me !== null && $me['username'] === $challenge['username']): ?>
        <p class="muted mt-24" style="font-size:14px">This is your own maze. Send this page's link to a friend to challenge them.</p>
        <a class="btn btn-secondary btn-lg btn-block mt-16" href="<?= url('dashboard') ?>">Back to dashboard</a>
      <?php elseif ($me !== null): ?>
        <a class="btn btn-primary btn-lg btn-block mt-24" href="<?= url(ltrim($next, '/')) ?>"><?= icon('play') ?>Accept challenge</a>
      <?php else: ?>
        <a class="btn btn-primary btn-lg btn-block mt-24" href="<?= url('register') ?>?next=<?= rawurlencode($next) ?>">Create a free account to play</a>
        <p class="auth-foot">Already playing? <a href="<?= url('user') ?>?next=<?= rawurlencode($next) ?>">Log in</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../layouts/document-end.php'; ?>
