<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Leaderboard — MazeQuest</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
<header class="navbar">
  <div class="logo">🎮 MazeQuest</div>
  <nav class="navlinks">
    <a href="<?= url('index#features') ?>">Features</a>
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

    <!-- Sample leaderboard entries -->
    <div class="leader-row top1">
      <div class="rank">1</div>
      <div class="player">MazeMaster</div>
      <div class="xp">9800</div>
      <div class="coins">💰 1520</div>
    </div>
    <div class="leader-row top2">
      <div class="rank">2</div>
      <div class="player">Brainiac</div>
      <div class="xp">8750</div>
      <div class="coins">💰 1240</div>
    </div>
    <div class="leader-row top3">
      <div class="rank">3</div>
      <div class="player">QuizHero</div>
      <div class="xp">8420</div>
      <div class="coins">💰 980</div>
    </div>
    <div class="leader-row">
      <div class="rank">4</div>
      <div class="player">PathFinder</div>
      <div class="xp">7920</div>
      <div class="coins">💰 860</div>
    </div>
    <div class="leader-row">
      <div class="rank">5</div>
      <div class="player">Smartie</div>
      <div class="xp">7600</div>
      <div class="coins">💰 740</div>
    </div>
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
        <li><a href="<?= url('index#features') ?>">Features</a></li>
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
</body>
</html>
