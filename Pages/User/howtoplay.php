<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>How to Play — MazeQuest</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
<header class="navbar">
  <div class="logo">🎮 MazeQuest</div>
  <nav class="navlinks">
    <a href="<?= url('#features') ?>">Features</a>
    <a href="<?= url('howtoplay') ?>" class="active">How to Play</a>
    <a href="<?= url('index#leader') ?>">Leaderboard</a>
  </nav>
  <div class="auth-buttons">
    <a href="<?= url('user') ?>" class="login">Login</a>
    <a href="<?= url('register') ?>" class="register">Register</a>
  </div>
</header>

<main>
  <section class="howto-hero">
    <h1>How to Play</h1>
    <p>Master the maze in 4 simple steps</p>
  </section>

  <section class="steps-section">
    <div class="step-card">
      <div class="num">1</div>
      <div>
        <h3>Choose Your Subject</h3>
        <p>Select from Math, Science, GK, History, or Mixed Mode to test your knowledge.</p>
      </div>
    </div>
    <div class="step-card">
      <div class="num">2</div>
      <div>
        <h3>Navigate the Maze</h3>
        <p>Use arrow keys or touch controls to move and find checkpoints.</p>
      </div>
    </div>
    <div class="step-card">
      <div class="num">3</div>
      <div>
        <h3>Answer Questions</h3>
        <p>Correct answers unlock paths and give you coins. Wrong answers have penalties!</p>
      </div>
    </div>
    <div class="step-card">
      <div class="num">4</div>
      <div>
        <h3>Reach the Exit</h3>
        <p>Find your way out before time runs out. Earn XP, coins, and achievements!</p>
      </div>
    </div>
  </section>

  <section class="difficulty-section">
    <h2>Difficulty Levels</h2>
    <div class="difficulty-grid">
      <div class="level easy">
        <h4>😊 Easy</h4>
        <p>Small maze, simple questions, no timer</p>
      </div>
      <div class="level medium">
        <h4>⚡ Medium</h4>
        <p>Bigger maze, timer countdown, penalties</p>
      </div>
      <div class="level hard">
        <h4>🔥 Hard</h4>
        <p>Large maze, fog of war, strict penalties</p>
      </div>
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
</body>
</html>
