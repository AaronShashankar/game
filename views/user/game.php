<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MazeQuest Game (Dynamic Maze)</title>
<style>
body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    background: linear-gradient(135deg, #1c2a38, #0a1016);
    color: #e0e0e0;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: background 1s;
    overflow-x: hidden;
}
h1, h3 { color: #87ceeb; }
canvas {
    border: 5px solid #87ceeb;
    margin-top: 20px;
    background: #1a1a2e;
    box-shadow: 0 0 25px rgba(135, 206, 235, 0.5);
}
#hud {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    flex-wrap: wrap;
}
#timer.low-time { color: #ff6b6b; animation: pulse 0.8s infinite alternate; }
#timer.frozen { background: #2a6f97; box-shadow: 0 0 15px rgba(135, 206, 235, 0.8); }
@keyframes pulse { from { transform: scale(1); } to { transform: scale(1.08); } }
#hud div {
    background: #1c375c;
    color: #fff;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: transform 0.2s, box-shadow 0.2s, color 0.3s;
    position: relative;
}
#hud div.flash {
    transform: scale(1.2);
    box-shadow: 0 0 25px gold;
    color: #ffff00;
}
.popup {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) scale(0);
    background: #1a1a2e;
    color: #fff;
    padding: 20px 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(135,206,235,0.5);
    transition: transform 0.3s;
    z-index: 1000;
    border: 2px solid #87ceeb;
    text-align: center;
    max-width: 90%;
    max-height: 90%;
    overflow-y: auto;
    pointer-events: none; /* prevent hidden popup from blocking input */
    visibility: hidden;  /* hide when not shown */
}
.popup.show { transform: translate(-50%, -50%) scale(1); pointer-events: auto; visibility: visible; }
.popup button {
    margin: 5px;
    padding: 8px 20px;
    cursor: pointer;
    background: #87ceeb;
    color: #1a1a2e;
    border: none;
    border-radius: 5px;
    font-weight: bold;
}

/* Special styling for correct answer popup */
#correctAnswerPopup {
    border-color: #4CAF50;
    box-shadow: 0 5px 20px rgba(76, 175, 80, 0.5);
    background: #1a2e1a;
}

#correctAnswerPopup h2 {
    color: #4CAF50;
}

#correctAnswerPopup .reward-amount {
    color: gold;
    font-weight: bold;
    font-size: 1.2em;
}

#shop {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}
#shop button {
    padding: 8px 15px;
    cursor: pointer;
    background: #3a506b;
    color: #fff;
    border: none;
    border-radius: 5px;
    position: relative;
    min-width: 120px;
}
#shop button:hover { background: #5bc0be; }
#shop button:disabled {
    background: #2a3a4b;
    color: #888;
    cursor: not-allowed;
}
.power-up-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: gold;
    color: #1a1a2e;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}
.coinXP {
    position: absolute;
    font-weight: bold;
    pointer-events: none;
    animation: flyUp 1s forwards;
}
@keyframes flyUp {
    0% {opacity:1; transform: translateY(0px);}
    100% {opacity:0; transform: translateY(-50px);}
}
.navbar {
    display:flex; justify-content:space-between; align-items:center; width:100%; padding:10px 20px; background:#162447; color:#fff;
}
.navbar a { color:#fff; margin:0 10px; text-decoration:none;}
.navbar a:hover {color:#ffd166;}
.navbar .logo {font-size:24px;}
.power-up-active {
    animation: pulse 1s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Shop Styles */
.shop-item {
    background: #2a3a4b;
    padding: 15px;
    border-radius: 10px;
    margin: 10px;
    text-align: center;
    min-width: 150px;
}
.shop-item button {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 8px;
}
.shop-item button:hover {
    background: #45a049;
}
.shop-item button:disabled {
    background: #666;
    cursor: not-allowed;
}
.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
    width: 100%;
    max-width: 800px;
}
.coin-display {
    font-size: 1.2em;
    font-weight: bold;
    color: gold;
    margin: 10px 0;
}

/* Subject Selection Styles */
.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
    max-width: 800px;
    width: 100%;
}
.subject-card {
    background: #2a3a4b;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.subject-card:hover {
    transform: translateY(-5px);
    border-color: #87ceeb;
    box-shadow: 0 10px 25px rgba(135, 206, 235, 0.3);
}
.subject-card.selected {
    border-color: #ffd166;
    background: #3a4a5b;
    box-shadow: 0 10px 25px rgba(255, 209, 102, 0.3);
}
.subject-icon {
    font-size: 40px;
    margin-bottom: 10px;
}
.subject-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}
.subject-desc {
    font-size: 12px;
    color: #aaa;
}
</style>
  <?= app_scripts() ?>
</head>
<body>

<header class="navbar">
  <div class="logo">🎮 MazeQuest</div>
  <div>
    <a href="<?= url('') ?>">Home</a>
    <a href="<?= url('dashboard') ?>">Dashboard</a>
    <a href="<?= url('profile') ?>">Profile</a>
    <a href="#" onclick="showShop()">Shop</a>
    <a href="<?= url('dashboard?openSpin=1') ?>">Spin</a>
  </div>
  <div>💰 <span id="coinsHUD">0</span></div>
</header>

<h1>MazeQuest — Dynamic Maze</h1>

<!-- Subject Selection Section -->
<div id="subjectSelection" style="text-align: center; margin: 20px 0;">
    <h2>📚 Choose Your Subject</h2>
    <p>Select a subject to start playing. Each gate will have questions from your chosen subject!</p>
    
    <div class="subject-grid">
        <div class="subject-card" onclick="selectSubject(event, 'Mathematics')">
            <div class="subject-icon">🔢</div>
            <div class="subject-name">Mathematics</div>
            <div class="subject-desc">Numbers, calculations, and problem solving</div>
        </div>
        
        <div class="subject-card" onclick="selectSubject(event, 'Science')">
            <div class="subject-icon">🔬</div>
            <div class="subject-name">Science</div>
            <div class="subject-desc">Physics, chemistry, and biology</div>
        </div>
        
        <div class="subject-card" onclick="selectSubject(event, 'General Knowledge')">
            <div class="subject-icon">🌍</div>
            <div class="subject-name">General Knowledge</div>
            <div class="subject-desc">World facts, geography, and culture</div>
        </div>
        
        <div class="subject-card" onclick="selectSubject(event, 'History')">
            <div class="subject-icon">🏛️</div>
            <div class="subject-name">History</div>
            <div class="subject-desc">Historical events and civilizations</div>
        </div>
        
        <div class="subject-card" onclick="selectSubject(event, 'English Vocabulary')">
            <div class="subject-icon">📖</div>
            <div class="subject-name">English Vocabulary</div>
            <div class="subject-desc">Words, synonyms, and language skills</div>
        </div>
        
        <div class="subject-card" onclick="selectSubject(event, 'Mixed Mode')">
            <div class="subject-icon">🎯</div>
            <div class="subject-name">Mixed Mode</div>
            <div class="subject-desc">All subjects combined for challenge</div>
        </div>
    </div>
    
    <button id="startGameBtn" onclick="startGameWithSubject()" style="
        background: #ffd166;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 20px;
        display: none;
    ">Start Game 🚀</button>
</div>

<!-- Game Section (Initially Hidden) -->
<div id="gameSection" style="display: none;">
    <div id="hud">
        <div>Coins: <span id="coins">0</span></div>
        <div>XP: <span id="xp">0</span></div>
        <div>Streak: <span id="streak">0</span></div>
        <div>Level: <span id="levelDisplay">1</span></div>
        <div id="timer">Time: <span id="timeLeft">60</span></div>
    </div>

    <canvas id="mazeCanvas" width="600" height="600" tabindex="0"></canvas>

    <div id="questionPopup" class="popup">
        <div id="questionText"></div>
        <div id="options"></div>
    </div>

    <div id="levelCompletePopup" class="popup">
        <h2>🎁 Level Complete!</h2>
        <p>You reached the gift and earned bonuses!</p>
        <button onclick="nextLevel()">Next Level</button>
        <button onclick="restartLevel()">Restart</button>
        <button onclick="goToDashboard()">Dashboard</button>
    </div>

    <div id="correctAnswerPopup" class="popup">
        <h2>✅ Correct Answer!</h2>
        <p>You earned <span class="reward-amount">+10</span> coins!</p>
        <button onclick="document.getElementById('correctAnswerPopup').classList.remove('show')">Continue</button>
    </div>

    <h3>Power-Ups</h3>
    <div id="shop">
        <button onclick="usePower('timeFreeze')" id="timeFreezeBtn">
            ⏱️ Time Freeze
            <span class="power-up-count" id="timeFreezeCount">0</span>
        </button>
        <button onclick="usePower('hint')" id="hintBtn">
            🔍 Hint Key
            <span class="power-up-count" id="hintCount">0</span>
        </button>
        <button onclick="usePower('skip')" id="skipBtn">
            ⏩ Skip Gate
            <span class="power-up-count" id="skipCount">0</span>
        </button>
        <button onclick="usePower('scoreDoubler')" id="scoreDoublerBtn">
            💰 Score Doubler
            <span class="power-up-count" id="scoreDoublerCount">0</span>
        </button>
        <button onclick="showShop()" style="background: #ff6b6b;">
            🛍️ Buy More
        </button>
    </div>
</div>

<!-- Shop Popup -->
<div id="shopPopup" class="popup">
    <h2>🛍️ Power-Up Shop</h2>
    <div class="coin-display">Your Coins: <span id="shopCoins">0</span> 💰</div>
    
    <div class="shop-grid">
        <div class="shop-item">
            <h3>⏱️ Time Freeze</h3>
            <p>Freeze the timer for 10 seconds</p>
            <p>Price: 25 coins</p>
            <p>You have: <span id="shopTimeFreezeCount">0</span></p>
            <button onclick="buyPowerUp('timeFreeze', 25)">Buy</button>
        </div>
        
        <div class="shop-item">
            <h3>🔍 Hint Key</h3>
            <p>Highlight nearest gate</p>
            <p>Price: 20 coins</p>
            <p>You have: <span id="shopHintCount">0</span></p>
            <button onclick="buyPowerUp('hint', 20)">Buy</button>
        </div>
        
        <div class="shop-item">
            <h3>⏩ Skip Gate</h3>
            <p>Skip one question gate</p>
            <p>Price: 30 coins</p>
            <p>You have: <span id="shopSkipCount">0</span></p>
            <button onclick="buyPowerUp('skip', 30)">Buy</button>
        </div>
        
        <div class="shop-item">
            <h3>💰 Score Doubler</h3>
            <p>Double next reward</p>
            <p>Price: 40 coins</p>
            <p>You have: <span id="shopScoreDoublerCount">0</span></p>
            <button onclick="buyPowerUp('scoreDoubler', 40)">Buy</button>
        </div>
        
        <div class="shop-item">
            <h3>🎒 Power-Up Pack</h3>
            <p>1 of each power-up</p>
            <p>Price: 100 coins</p>
            <button onclick="buyPowerUpPack()">Buy Pack</button>
        </div>
        
        <div class="shop-item">
            <h3>💎 Mega Pack</h3>
            <p>3 of each power-up</p>
            <p>Price: 250 coins</p>
            <button onclick="buyMegaPack()">Buy Mega</button>
        </div>
    </div>
    
    <button onclick="hideShop()" style="margin-top: 20px;">Close Shop</button>
</div>

<script>
  // Display settings and subjects from the server (GameRules / questions table).
  window.GameConfig = <?= json_encode(['difficulties' => $difficulties, 'subjects' => $subjects], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= page_script('game') ?>
</body>
</html>
