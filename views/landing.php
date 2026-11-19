<?php
use App\Services\GameRules;

/** @var array $difficulties  GameRules::DIFFICULTIES */
/** @var string[] $subjects */
$active = 'home';
require __DIR__ . '/layouts/header.php';
$diffCopy = [
    'Easy'   => 'A small maze with no clock. Good for learning the ropes.',
    'Medium' => 'A bigger maze against the clock. Wrong answers cost coins.',
    'Hard'   => 'A large maze in fog of war. You only see what is near you.',
];
?>

<section class="container hero">
  <div>
    <p class="eyebrow">An educational maze game</p>
    <h1>Learn by finding your way out.</h1>
    <p class="hero-sub">Walk the maze, answer a question at every gate, and reach the exit. Right answers earn coins and XP. Guessing doesn't.</p>
    <div class="hero-cta">
      <a class="btn btn-primary btn-lg" href="<?= url('register') ?>"><?= icon('play') ?>Start playing — it's free</a>
      <a class="btn btn-secondary btn-lg" href="<?= url('howtoplay') ?>">How it works</a>
    </div>
    <dl class="hero-facts">
      <div><dt><?= count($subjects) ?></dt><dd>Subjects</dd></div>
      <div><dt><?= count($difficulties) ?></dt><dd>Difficulty levels</dd></div>
      <div><dt>∞</dt><dd>Mazes, each one new</dd></div>
    </dl>
  </div>
  <div class="hero-art"><?php require __DIR__ . '/partials/maze-art.php'; ?></div>
</section>

<section class="container section" id="features">
  <div class="section-head">
    <p class="eyebrow">Features</p>
    <h2 class="section-title">Simple to pick up, rewarding to master</h2>
  </div>
  <div class="feature-grid">
    <div class="card feature"><div class="icon-box"><?= icon('bulb') ?></div><h3>Questions at every gate</h3><p>Gates block the path to the exit. Answer correctly to walk through.</p></div>
    <div class="card feature"><div class="icon-box"><?= icon('target') ?></div><h3>Fair scoring</h3><p>Full reward on the first try, half on the second, none after that.</p></div>
    <div class="card feature"><div class="icon-box"><?= icon('flame') ?></div><h3>Daily streaks</h3><p>Check in every day to grow your streak and your bonus.</p></div>
    <div class="card feature"><div class="icon-box"><?= icon('trophy') ?></div><h3>Leaderboards</h3><p>Climb the all-time and weekly rankings for XP, coins and streaks.</p></div>
  </div>
</section>

<section class="container section">
  <div class="section-head">
    <p class="eyebrow">Difficulty</p>
    <h2 class="section-title">Pick your challenge</h2>
    <p class="section-sub">Every level generates a new maze. Harder levels multiply your rewards.</p>
  </div>
  <div class="diff-grid">
    <?php foreach ($difficulties as $name => $d): ?>
      <div class="card">
        <div class="row-between">
          <h3 style="font-size:17px"><?= e($name) ?></h3>
          <span class="chip<?= $name === 'Medium' ? ' chip-accent' : '' ?>">×<?= rtrim(rtrim(number_format($d['mult'], 1), '0'), '.') ?> rewards</span>
        </div>
        <p class="muted mt-8" style="font-size:14px"><?= e($diffCopy[$name] ?? '') ?></p>
        <dl class="spec">
          <div><dt>Maze</dt><dd><?= $d['size'] ?> × <?= $d['size'] ?></dd></div>
          <div><dt>Gates</dt><dd><?= $d['gates'] ?></dd></div>
          <div><dt>Time limit</dt><dd><?= $d['time'] ? floor($d['time'] / 60) . ':' . str_pad((string) ($d['time'] % 60), 2, '0') : 'None' ?></dd></div>
          <div><dt>Fog of war</dt><dd><?= $d['fog'] ? 'Yes' : 'No' ?></dd></div>
        </dl>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <div class="grid grid-2" style="align-items:start; gap:48px">
    <div>
      <p class="eyebrow">Leaderboard</p>
      <h2 class="section-title">Top explorers</h2>
      <p class="section-sub">Ranked by lifetime XP. Every point was earned in a verified game.</p>
      <a href="<?= url('leaderboard') ?>" class="btn btn-secondary mt-24">See full leaderboard <?= icon('arrow-right', 'icon-sm') ?></a>
    </div>
    <div class="card" style="padding:8px 20px">
      <div class="list" id="leader-list-landing"><div class="empty">Loading…</div></div>
    </div>
  </div>
</section>

<section class="container section">
  <div class="cta-band">
    <div>
      <h2>Ready to find the exit?</h2>
      <p>Create an account and get <?= GameRules::STARTER_COINS ?> coins and <?= GameRules::STARTER_POWERUPS ?> of every power-up to start.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= url('register') ?>">Create free account</a>
  </div>
</section>

<?= page_script('landing') ?>
<?php require __DIR__ . '/layouts/footer.php'; ?>
