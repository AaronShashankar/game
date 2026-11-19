<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>MazeQuest — Game Shop</title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  background: linear-gradient(135deg, #f3e7ff 0%, #fff2f2 100%);
  color: #222;
  min-height: 100vh;
  padding-bottom: 60px;
}

main {
  max-width: 1200px;
  margin: 40px auto;
  padding: 0 20px;
}

main h1 {
  font-size: 42px;
  font-weight: 800;
  margin-bottom: 10px;
  background: linear-gradient(135deg, #6b46ff, #9b59ff);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-align: center;
}

.shop-section {
  margin-bottom: 50px;
  background: white;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.shop-section h2 {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 30px;
  color: #222;
  padding-bottom: 15px;
  border-bottom: 3px solid #6b46ff;
}

.item-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 25px;
}

.item {
  background: linear-gradient(135deg, #f8f9ff, #fff5f5);
  padding: 25px;
  border-radius: 16px;
  text-align: center;
  transition: all 0.3s;
  border: 2px solid transparent;
  position: relative;
  overflow: hidden;
}

.item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #6b46ff, #9b59ff);
  transform: scaleX(0);
  transition: transform 0.3s;
}

.item:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 35px rgba(107, 70, 255, 0.2);
  border-color: #6b46ff;
}

.item:hover::before {
  transform: scaleX(1);
}

.item .item-icon {
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 64px;
  margin: 0 auto 15px;
  border-radius: 12px;
  margin-bottom: 15px;
  border: 3px solid #e0e0e0;
  transition: all 0.3s;
}

.item:hover img {
  border-color: #6b46ff;
  transform: scale(1.05);
}

.item h3 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 10px;
  color: #222;
}

.item p {
  font-size: 14px;
  color: #666;
  margin-bottom: 15px;
  line-height: 1.6;
  min-height: 40px;
}

.buy-btn {
  margin-top: 10px;
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #6b46ff, #9b59ff);
  color: white;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s;
  width: 100%;
  box-shadow: 0 4px 15px rgba(107, 70, 255, 0.3);
}

.buy-btn:hover:not(.owned) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(107, 70, 255, 0.4);
}

.buy-btn.owned {
  background: linear-gradient(135deg, #4CAF50, #66BB6A);
  color: white;
  cursor: default;
  box-shadow: none;
}

.buy-btn.owned:hover {
  transform: none;
}

.coins {
  font-size: 18px;
  font-weight: 700;
  color: #ffd700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.coins span {
  color: #222;
}

header .auth-buttons .coins {
  background: linear-gradient(135deg, #ffd700, #ffed4e);
  padding: 8px 16px;
  border-radius: 20px;
  color: #222;
  font-weight: 700;
  box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
}

@media (max-width: 768px) {
  .item-grid {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
  }

  .shop-section {
    padding: 25px 20px;
  }

  main h1 {
    font-size: 32px;
  }
}
.shop-message { min-height: 1.5em; text-align: center; font-weight: 600; }
.item .owned-count { font-size: 0.9em; opacity: 0.75; }
</style>
  <?= app_scripts() ?>
</head>
<body>

<header>
  <div class="logo">🎮 MazeQuest</div>
  <nav class="navlinks">
    <a href="<?= url('') ?>">Home</a>
    <a href="<?= url('game') ?>">Play</a>
    <a href="<?= url('leaderboard') ?>">Leaderboard</a>
    <a href="<?= url('shop') ?>" class="active">Shop</a>
  </nav>
  <div class="auth-buttons">
    <div class="coins">💰 <span id="coinCount">0</span></div>
    <a href="<?= url('profile') ?>" class="login">Profile</a>
  </div>
</header>

<main>
  <h1>MazeQuest Shop</h1>
  <p id="shopMessage" class="shop-message" role="status"></p>
  <!-- Filled from /api/shop: prices and ownership come from the server. -->
  <section class="shop-section"><h2>⚡ Power-ups</h2><div class="item-grid" data-kinds="powerup,pack"></div></section>
  <section class="shop-section"><h2>👕 Player Skins</h2><div class="item-grid" data-kinds="skin"></div></section>
  <section class="shop-section"><h2>🧭 Maze Themes</h2><div class="item-grid" data-kinds="theme"></div></section>
</main>

<footer>
  <p>© <?= date('Y') ?> MazeQuest. All Rights Reserved.</p>
</footer>

<?= page_script('shop') ?>

</body>
</html>
