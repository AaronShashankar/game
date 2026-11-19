<?php
/**
 * Logged-in player pages: sidebar + top bar.
 * @var string $active  'dashboard' | 'play' | 'shop' | 'leaderboard' | 'profile'
 * @var string $title
 */
$me = current_player();
$nav = [
    'dashboard'   => ['Dashboard',   'grid',   'dashboard'],
    'play'        => ['Play',        'play',   'choose-subject'],
    'shop'        => ['Shop',        'bag',    'shop'],
    'leaderboard' => ['Leaderboard', 'trophy', 'leaderboard'],
    'profile'     => ['Profile',     'user',   'profile'],
];
require __DIR__ . '/document-start.php';
?>
<div class="shell">
  <aside class="sidebar">
    <a href="<?= url('dashboard') ?>" aria-label="Dashboard"><?= brand() ?></a>
    <nav class="side-nav" aria-label="Main">
      <?php foreach ($nav as $key => [$label, $ico, $path]): ?>
        <a href="<?= url($path) ?>" class="side-link<?= ($active ?? '') === $key ? ' is-active' : '' ?>"<?= ($active ?? '') === $key ? ' aria-current="page"' : '' ?>>
          <?= icon($ico) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('howtoplay') ?>" class="side-link"><?= icon('book') ?><span>How to play</span></a>
      <button type="button" class="side-link" onclick="logout()"><?= icon('logout') ?><span>Log out</span></button>
    </div>
  </aside>

  <div class="shell-main">
    <header class="topbar">
      <div class="topbar-title"><?= e($title ?? '') ?></div>
      <div class="topbar-actions">
        <span class="pill" title="Coins"><?= icon('coins') ?><span id="shellCoins" class="num"><?= (int) ($me['coins'] ?? 0) ?></span></span>
        <?= theme_toggle() ?>
        <a href="<?= url('profile') ?>" class="avatar-initial hide-sm" title="<?= e($me['username'] ?? '') ?>"><?= e(strtoupper(substr($me['username'] ?? '?', 0, 1))) ?></a>
      </div>
    </header>
    <main class="page">
