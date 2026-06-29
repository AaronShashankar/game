<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>MazeQuest — Game Shop</title>
<link rel="stylesheet" href="assets/style.css" />
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

.item img {
  width: 120px;
  height: 120px;
  object-fit: cover;
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
</style>
</head>
<body>

<header>
  <div class="logo">🎮 MazeQuest</div>
  <nav class="navlinks">
    <a href="<?= url('index') ?>">Home</a>
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
  <section class="shop-section">
    <h2>👕 Player Skins</h2>
    <div class="item-grid">
      <div class="item">
        <img src="assets/skins/skin1.png" alt="Classic Skin">
        <h3>Classic Explorer</h3>
        <p>Default look. Timeless adventure vibe!</p>
        <button class="buy-btn" data-key="skin1" data-price="0">Owned</button>
      </div>
      <div class="item">
        <img src="assets/skins/skin2.png" alt="Blue Skin">
        <h3>Blue Runner</h3>
        <p>Cool and confident for fast mazes.</p>
        <button class="buy-btn" data-key="skin2" data-price="200">Buy – 200💰</button>
      </div>
      <div class="item">
        <img src="assets/skins/skin3.png" alt="Red Hero">
        <h3>Red Hero</h3>
        <p>Be the bold explorer who never gives up!</p>
        <button class="buy-btn" data-key="skin3" data-price="350">Buy – 350💰</button>
      </div>
    </div>
  </section>

  <section class="shop-section">
    <h2>🧭 Maze Themes</h2>
    <div class="item-grid">
      <div class="item">
        <img src="assets/themes/forest.png" alt="Forest Theme">
        <h3>Forest Maze</h3>
        <p>Green and mysterious forest paths.</p>
        <button class="buy-btn" data-key="forest" data-price="300">Buy – 300💰</button>
      </div>
      <div class="item">
        <img src="assets/themes/space.png" alt="Space Theme">
        <h3>Space Maze</h3>
        <p>Explore mazes among stars and planets!</p>
        <button class="buy-btn" data-key="space" data-price="450">Buy – 450💰</button>
      </div>
    </div>
  </section>

  <section class="shop-section">
    <h2>⚡ Power-ups</h2>
    <div class="item-grid">
      <div class="item">
        <img src="assets/powerups/speed.png" alt="Speed Boost">
        <h3>Speed Boost</h3>
        <p>Move faster for 30 seconds!</p>
        <button class="buy-btn" data-key="speedBoost" data-price="150">Buy – 150💰</button>
      </div>
      <div class="item">
        <img src="assets/powerups/shield.png" alt="Shield">
        <h3>Shield</h3>
        <p>Protects you from traps once.</p>
        <button class="buy-btn" data-key="shield" data-price="250">Buy – 250💰</button>
      </div>
    </div>
  </section>
</main>

<footer>
  <p>© 2025 MazeQuest. All Rights Reserved.</p>
</footer>

<script>
let user = JSON.parse(localStorage.getItem("user")) || {coins:100,powerUps:{},skins:[],themes:[]};
const coinDisplay = document.getElementById('coinCount');
const buttons = document.querySelectorAll('.buy-btn');

function updateShopUI(){
  coinDisplay.textContent = user.coins;
  buttons.forEach(btn=>{
    const key = btn.dataset.key;
    if(user.skins?.includes(key) || user.themes?.includes(key) || user.powerUps?.[key]>0 || btn.dataset.price=="0"){
      btn.textContent="Owned"; btn.classList.add("owned");
    } else {
      btn.textContent=`Buy – ${btn.dataset.price}💰`; btn.classList.remove("owned");
    }
  });
}

buttons.forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const key = btn.dataset.key;
    const price = parseInt(btn.dataset.price);
    if(price==0 || btn.textContent==="Owned") return;

    if(user.coins>=price){
      user.coins -= price;
      if(key.startsWith('skin')) user.skins = user.skins || [], user.skins.push(key);
      else if(key==='forest' || key==='space') user.themes = user.themes || [], user.themes.push(key);
      else user.powerUps[key] = (user.powerUps[key]||0)+1;
      alert("Purchase successful! 🎉");
      updateShopUI();
      localStorage.setItem("user",JSON.stringify(user));
    } else alert("Not enough coins 💰");
  });
});

updateShopUI();
</script>

</body>
</html>
