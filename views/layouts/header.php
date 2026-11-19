<?php
/**
 * Public pages (landing, how to play, leaderboard).
 * @var string|null $active  'home' | 'howtoplay' | 'leaderboard'
 */
$active = $active ?? '';
$signedIn = \App\Http\Auth::playerId() !== null;
require __DIR__ . '/document-start.php';
?>
<header class="site-header">
  <div class="container">
    <a href="<?= url('') ?>" aria-label="MazeQuest home"><?= brand() ?></a>
    <nav class="site-nav" aria-label="Main">
      <a href="<?= url('') ?>#features" class="<?= $active === 'home' ? 'is-active' : '' ?>">Features</a>
      <a href="<?= url('howtoplay') ?>" class="<?= $active === 'howtoplay' ? 'is-active' : '' ?>">How to play</a>
      <a href="<?= url('leaderboard') ?>" class="<?= $active === 'leaderboard' ? 'is-active' : '' ?>">Leaderboard</a>
    </nav>
    <div class="site-actions">
      <?= theme_toggle() ?>
      <?php if ($signedIn): ?>
        <a href="<?= url('dashboard') ?>" class="btn btn-primary btn-sm">Open dashboard</a>
      <?php else: ?>
        <a href="<?= url('user') ?>" class="btn btn-ghost btn-sm">Log in</a>
        <a href="<?= url('register') ?>" class="btn btn-primary btn-sm">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
