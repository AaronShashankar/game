<?php
$pageTitle = $pageTitle ?? 'MazeQuest';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= asset('css/global.css') ?>" />
  <?php if(isset($extraCss)): ?>
    <link rel="stylesheet" href="<?= e(asset($extraCss)) ?>" />
  <?php endif; ?>
  <style>
    /* Default simple nav for backwards compatibility in refactor */
    .navbar { display: flex; justify-content: space-between; padding: 10px 20px; background: var(--card-bg); border-bottom: 1px solid var(--border-color); }
    .navlinks a { margin: 0 10px; text-decoration: none; color: var(--text-color); font-weight: bold; }
  </style>
  <?= app_scripts() ?>
</head>
<body>
  <header class="navbar">
    <div class="logo"><a href="<?= url('') ?>" style="text-decoration:none; color:inherit;">🎮 MazeQuest</a></div>
    <nav class="navlinks">
      <a href="<?= url('#features') ?>">Features</a>
      <a href="<?= url('howtoplay') ?>">How to Play</a>
      <a href="<?= url('leaderboard') ?>">Leaderboard</a>
    </nav>
    <div class="auth-buttons">
      <?php if (\App\Http\Auth::playerId() !== null): ?>
        <a href="<?= url('dashboard') ?>" class="btn btn-outline" style="margin-right:10px;">Dashboard</a>
        <a href="<?= url('profile') ?>" class="btn btn-outline" style="margin-right:10px;">Profile</a>
        <a href="#" class="btn btn-primary" onclick="logout()">Logout</a>
      <?php else: ?>
        <a href="<?= url('user') ?>" class="login btn btn-outline" style="margin-right:10px;">Login</a>
        <a href="<?= url('register') ?>" class="register btn btn-primary">Register</a>
      <?php endif; ?>
      <button class="theme-toggle" onclick="toggleTheme()" style="margin-left:15px; background:none; border:none; cursor:pointer; font-size:1.2rem;">🌓</button>
    </div>
  </header>
  <main style="padding: 20px;">
