<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Leaderboard — MazeQuest</title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>" />
  <?= app_scripts() ?>
</head>
<body>
<header class="navbar">
  <div class="logo">🎮 MazeQuest</div>
  <nav class="navlinks">
    <a href="<?= url('#features') ?>">Features</a>
    <a href="<?= url('howtoplay') ?>">How to Play</a>
    <a href="<?= url('leaderboard') ?>" class="active">Leaderboard</a>
  </nav>
  <div class="auth-buttons">
    <a href="<?= url('user') ?>" class="login">Login</a>
    <a href="<?= url('register') ?>" class="register">Register</a>
  </div>
</header>

<main class="leaderboard-container">
  <section class="leader-hero">
    <h1>🏆 Top Players</h1>
    <p>See who’s mastered the maze! Compete for XP, coins, and glory.</p>
  </section>

  <section class="leaderboard-table">
    <div class="leader-header">
      <div class="rank">#</div>
      <div class="player">Player</div>
      <div class="xp">XP</div>
      <div class="coins">Coins</div>
    </div>
    <div id="leaderRows"></div>
  </section>
</main>

<footer>
  <div class="footer-content">
    <div class="col">
      <h3>🎮 MazeQuest</h3>
      <p>Learn while playing. The ultimate educational maze adventure.</p>
    </div>
    <div class="col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="<?= url('#features') ?>">Features</a></li>
        <li><a href="<?= url('howtoplay') ?>">How To Play</a></li>
        <li><a href="<?= url('game') ?>">Play</a></li>
      </ul>
    </div>
    <div class="col">
      <h4>Game</h4>
      <ul>
        <li><a href="<?= url('user') ?>">Login</a></li>
        <li><a href="<?= url('register') ?>">Register</a></li>
        <li><a href="<?= url('profile') ?>">Profile</a></li>
      </ul>
    </div>
    <div class="col">
      <h4>Connect</h4>
      <div class="social">
        <a href="#">🐦</a>
        <a href="#">📘</a>
        <a href="#">📸</a>
      </div>
    </div>
  </div>
</footer>
<?= page_script('leaderboard') ?>
</body>
</html>
