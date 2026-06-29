<?php
$pageTitle = 'MazeQuest — Learn & Play';
require_once __DIR__ . '/../Components/header.php';
?>

  <section class="hero">
    <h1>Learn & Play in the <span class="highlight">Ultimate Maze</span></h1>
    <p>Solve puzzles, answer questions, and navigate through challenging mazes. Earn rewards, unlock skins, and compete with friends!</p>
    <div class="buttons">
      <a class="btn btn-primary" href="<?= url('user') ?>">▶ Play Now</a>
      <a class="btn btn-outline" href="<?= url('howtoplay') ?>">Learn How to Play</a>
    </div>

    <div class="stats">
      <div class="stat"><span class="num purple">10+</span><p>Subjects</p></div>
      <div class="stat"><span class="num pink">50+</span><p>Levels</p></div>
      <div class="stat"><span class="num orange">1000+</span><p>Players</p></div>
    </div>
  </section>

  <!-- Added modern Features & How to Play section -->
<section class="modern-section" id="features">
  <h2>Amazing Features</h2>
  <p class="section-intro">Discover what makes MazeQuest fun and educational!</p>
  <div class="grid-cards">
    <div class="card"><div class="icon purple">🎁</div><div><h3>Daily Rewards</h3><p>Earn coins each day you check in and play!</p></div></div>
    <div class="card"><div class="icon blue">🧠</div><div><h3>Smart Learning</h3><p>Quiz questions appear at checkpoints to boost knowledge.</p></div></div>
    <div class="card"><div class="icon pink">🏆</div><div><h3>Global Leaderboard</h3><p>Compete with players worldwide for top ranks.</p></div></div>
    <div class="card"><div class="icon yellow">👕</div><div><h3>Skins & Themes</h3><p>Customize your maze and avatar with rewards.</p></div></div>
  </div>
</section>

<section class="modern-section" id="howtoplay">
  <h2>How to Play</h2>
  <p class="section-intro">Master the maze in 4 simple steps</p>
  <div class="steps">
    <div class="step"><div class="num">1</div><div><h4>Select Mode</h4><p>Pick your subject or play in mixed mode.</p></div></div>
    <div class="step"><div class="num">2</div><div><h4>Navigate</h4><p>Use arrow keys or touch to move through the maze.</p></div></div>
    <div class="step"><div class="num">3</div><div><h4>Answer</h4><p>Reach checkpoints and solve quiz questions to unlock paths.</p></div></div>
    <div class="step"><div class="num">4</div><div><h4>Win</h4><p>Reach the exit to earn XP, coins, and unlock new levels!</p></div></div>
  </div>
</section>
<section id="features2" class="features">
    <div class="feature-cards">
      <div class="card easy">
        <h3>😊 Easy</h3>
        <p>Small maze, simple questions, no timer</p>
      </div>
      <div class="card medium">
        <h3>⚡ Medium</h3>
        <p>Bigger maze, timer countdown, penalties</p>
      </div>
      <div class="card hard">
        <h3>🔥 Hard</h3>
        <p>Large maze, fog of war, strict penalties</p>
      </div>
    </div>
  </section>

  <section id="leader" class="leader">
    <h2>Top Players (Local)</h2>
    <ol id="leader-list-landing"></ol>
  </section>

<script src="<?= url('assets/landing.js') ?>"></script>
<?php require_once __DIR__ . '/../Components/footer.php'; ?>
