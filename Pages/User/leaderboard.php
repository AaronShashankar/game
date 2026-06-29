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
<script>
// Fetch and render leaderboard
async function loadLeaderboard(type='xp'){
  try{
    const res = await fetch(`leaderboard.php?type=${encodeURIComponent(type)}`, { credentials: 'same-origin' });
    const data = await res.json();
    const rows = document.getElementById('leaderRows');
    rows.innerHTML = '';
    if(!data.success){
      rows.innerHTML = `<div class="leader-row"><div class="rank">-</div><div class="player">Error loading leaderboard</div><div class="xp">-</div><div class="coins">-</div></div>`;
      return;
    }
    data.leaderboard.forEach((p, idx) => {
      const div = document.createElement('div');
      div.className = `leader-row ${idx===0?'top1':idx===1?'top2':idx===2?'top3':''}`;
      const avatar = p.avatar_url ? `<img src="${p.avatar_url}" alt="" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:8px;">` : '';
      div.innerHTML = `
        <div class="rank">${idx+1}</div>
        <div class="player">${avatar}${p.username || 'Player'}</div>
        <div class="xp">${p.xp ?? 0}</div>
        <div class="coins">💰 ${p.coins ?? 0}</div>
      `;
      rows.appendChild(div);
    });
  }catch(e){
    const rows = document.getElementById('leaderRows');
    rows.innerHTML = `<div class="leader-row"><div class="rank">-</div><div class="player">Network error</div><div class="xp">-</div><div class="coins">-</div></div>`;
  }
}

document.addEventListener('DOMContentLoaded', ()=> loadLeaderboard('xp'));
</script>
</body>
</html>
