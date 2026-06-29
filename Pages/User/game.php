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
</head>
<body>

<header class="navbar">
  <div class="logo">🎮 MazeQuest</div>
  <div>
    <a href="<?= url('index') ?>">Home</a>
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
            <p>Freeze time for 5 seconds</p>
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
// ===== User & Game State (loaded from database) =====
let user = {
    coins: 100,
    xp: 50,
    streak: 0,
    level: 1,
    powerUps: {
        timeFreeze: 3,
        hint: 3,
        skip: 3,
        scoreDoubler: 3
    },
    skins: [],
    themes: [],
    badges: [],
    avatar: "https://i.pravatar.cc/50",
    history: [],
    weekly: {},
    lastCheckin: ""
};

// Backward compatibility: ensure missing fields exist on older saved users
if (!user.powerUps) {
    user.powerUps = { timeFreeze: 0, hint: 0, skip: 0, scoreDoubler: 0 };
}
// Ensure aggregate counters exist
user.total_games = typeof user.total_games === 'number' ? user.total_games : 0;
user.correct_answers = typeof user.correct_answers === 'number' ? user.correct_answers : 0;
user.wrong_answers = typeof user.wrong_answers === 'number' ? user.wrong_answers : 0;
user.powerups_used = typeof user.powerups_used === 'number' ? user.powerups_used : 0;

// ==== Backend Detection & Sync to server (debounced) ====
let backendAvailable = false;
let _syncTimer = null;
let _sessionId = null;  // if backend provides a session ID
let _accumScore = 0;   // coins earned during this session for leaderboard

async function checkBackend() {
    try {
        const res = await fetch('<?= url(''/api/'') ?>'sync_profile.php', { 
            method: 'POST',
            body: new FormData(),
            credentials: 'same-origin',
            signal: AbortSignal.timeout(2000)
        });
        backendAvailable = res.ok || res.status === 401;
        return backendAvailable;
    } catch(e) {
        backendAvailable = false;
        return false;
    }
}

async function startServerSession() {
    if (!backendAvailable) return;
    try {
        const form = new FormData();
        // If you have a specific maze_id from backend, pass it here. Default to 1.
        form.append('maze_id', 1);
        const res = await fetch('<?= url(''/api/'') ?>'game_session_api.php?action=start_session', { 
            method: 'POST', 
            body: form, 
            credentials: 'same-origin',
            signal: AbortSignal.timeout(3000)
        });
        const j = await res.json();
        if (j && j.success) { _sessionId = j.session_id; }
    } catch(e) { /* ignore */ }
}

async function endServerSession() {
    if (!_sessionId || !backendAvailable) return;
    try {
        const form = new FormData();
        form.append('session_id', _sessionId);
        form.append('score_earned', Math.max(0, Math.floor(_accumScore)));
        await fetch('<?= url(''/api/'') ?>'game_session_api.php?action=end_session', { 
            method: 'POST', 
            body: form, 
            credentials: 'same-origin',
            signal: AbortSignal.timeout(3000)
        });
    } catch(e) { /* ignore */ }
    _sessionId = null; _accumScore = 0;
}

async function syncProfileNow() {
    // Save to database only (no localStorage)
    if (!backendAvailable) {
        console.warn('⚠️ Backend not available - cannot save game data');
        return false;
    }
    
    try {
        const form = new FormData();
        form.append('username', user.username || '');
        form.append('email', user.email || '');
        form.append('coins', user.coins || 0);
        form.append('level', user.level || 1);
        form.append('xp', user.xp || 0);
        form.append('streak', user.streak || 0);
        form.append('avatar', user.avatar || '');
        form.append('last_checkin', user.lastCheckin || '');
        // Aggregate totals
        form.append('total_games', user.total_games || 0);
        form.append('correct_answers', user.correct_answers || 0);
        form.append('wrong_answers', user.wrong_answers || 0);
        form.append('powerups_used', user.powerups_used || 0);
        
        const res = await fetch('<?= url(''/api/'') ?>'sync_profile.php', { 
            method: 'POST', 
            body: form, 
            credentials: 'same-origin',
            signal: AbortSignal.timeout(5000)
        });
        
        if (res.ok) {
            const data = await res.json();
            if (data.success) {
                console.log('✅ Game data saved to database');
                return true;
            } else {
                console.error('❌ Game save failed:', data.message);
                return false;
            }
        }
        return false;
    } catch(e) { 
        console.error('❌ Game save error:', e.message);
        return false;
    }
}

function scheduleSync() {
    if (_syncTimer) clearTimeout(_syncTimer);
    _syncTimer = setTimeout(syncProfileNow, 800);
}

// Load user data from database
async function loadUserFromDB() {
    if (!backendAvailable) {
        console.error('❌ Backend not available - cannot load user data');
        return false;
    }
    
    try {
        const res = await fetch('<?= url(''/api/'') ?>'user_profile.php', { 
            credentials: 'same-origin',
            signal: AbortSignal.timeout(5000)
        });
        const data = await res.json();
        
        if (data && data.success && data.user) {
            user.username = data.user.username || 'Player1';
            user.email = data.user.email || '';
            user.coins = typeof data.user.coins === 'number' ? data.user.coins : 100;
            user.level = typeof data.user.level === 'number' ? data.user.level : 1;
            user.xp = typeof data.user.xp === 'number' ? data.user.xp : 0;
            user.streak = typeof data.user.streak === 'number' ? data.user.streak : 0;
            user.avatar = data.user.avatar || 'https://i.pravatar.cc/50';
            user.lastCheckin = data.user.lastCheckin || '';
            
            console.log('✅ Game loaded user data from database');
            return true;
        } else {
            console.error('❌ Failed to load user:', data.message);
            return false;
        }
    } catch(e) {
        console.error('❌ Load user error:', e.message);
        return false;
    }
}

// Initialize backend check and load user data
(async function() {
    await checkBackend();
    if (backendAvailable) {
        console.log('🌐 Backend available - loading user data from database');
        await loadUserFromDB();
        updateHUD();
    } else {
        console.error('❌ Backend not available - please login via localhost');
    }
})();

let selectedSubject = '';
let selectedDifficulty = 'Easy';
let scoreDoublerActive = false;
const TIME_BY_DIFF = { 'Easy': 60, 'Medium': 120, 'Hard': 180 };
let countdown = TIME_BY_DIFF[selectedDifficulty];
const canvas = document.getElementById('mazeCanvas');
const ctx = canvas.getContext('2d');
let rows = 10, cols = 10;
let cellSize = canvas.width/cols;
let maze=[],player={x:0,y:0},gates=[],wrongAttempts=0,isTimeFrozen=false;
let start={x:0,y:0},end={x:rows-1,y:cols-1};
let sparkles=[];

// ===== Admin-configured Maze View Settings =====
let mazeViewSettings = {};
try { mazeViewSettings = JSON.parse(localStorage.getItem('mazeViewSettings') || '{}'); } catch(e) { mazeViewSettings = {}; }
const WALL_COLOR = mazeViewSettings.wallColor || '#5bc0be';
const LINE_WIDTH = (typeof mazeViewSettings.lineWidth === 'number' ? mazeViewSettings.lineWidth : 4);
const GATE_DENSITY = (typeof mazeViewSettings.gateDensity === 'number' ? mazeViewSettings.gateDensity : 0.07);

// ===== Question Banks =====
const questionBanks = {
    "Mathematics": [
        {
            q: "What is 15 + 27?",
            options: ["42", "32", "52", "37"],
            answer: "42"
        },
        {
            q: "What is the square root of 144?",
            options: ["12", "14", "16", "11"],
            answer: "12"
        },
        {
            q: "Solve for x: 2x + 5 = 17",
            options: ["x = 6", "x = 7", "x = 8", "x = 9"],
            answer: "x = 6"
        },
        {
            q: "What is 30% of 200?",
            options: ["50", "60", "70", "80"],
            answer: "60"
        },
        {
            q: "What is the value of π (pi) to two decimal places?",
            options: ["3.14", "3.16", "3.12", "3.18"],
            answer: "3.14"
        },
        {
            q: "What is 8 × 7?",
            options: ["56", "54", "58", "60"],
            answer: "56"
        },
        {
            q: "What is 144 ÷ 12?",
            options: ["12", "10", "14", "16"],
            answer: "12"
        },
        {
            q: "What is 5² + 3³?",
            options: ["34", "32", "36", "38"],
            answer: "34"
        }
    ],
    "Science": [
        {
            q: "What planet is known as the Red Planet?",
            options: ["Venus", "Mars", "Jupiter", "Saturn"],
            answer: "Mars"
        },
        {
            q: "What is H2O commonly known as?",
            options: ["Oxygen", "Water", "Carbon Dioxide", "Hydrogen Peroxide"],
            answer: "Water"
        },
        {
            q: "What is the chemical symbol for gold?",
            options: ["Go", "Gd", "Au", "Ag"],
            answer: "Au"
        },
        {
            q: "Which gas do plants absorb from the atmosphere?",
            options: ["Oxygen", "Nitrogen", "Carbon Dioxide", "Hydrogen"],
            answer: "Carbon Dioxide"
        },
        {
            q: "What is the hardest natural substance on Earth?",
            options: ["Gold", "Iron", "Diamond", "Platinum"],
            answer: "Diamond"
        },
        {
            q: "What force pulls objects toward the Earth?",
            options: ["Magnetism", "Gravity", "Friction", "Electricity"],
            answer: "Gravity"
        },
        {
            q: "How many bones are in the human body?",
            options: ["206", "200", "210", "196"],
            answer: "206"
        }
    ],
    "General Knowledge": [
        {
            q: "नेपालको राजधानी कहाँ हो?",
            options: ["पोखरा", "ललितपुर", "काठमाडौं", "भरतपुर"],
            answer: "काठमाडौं"
        },
        {
            q: "What is the capital of France?",
            options: ["London", "Berlin", "Paris", "Madrid"],
            answer: "Paris"
        },
        {
            q: "Which country is known as the Land of the Rising Sun?",
            options: ["China", "Thailand", "Japan", "South Korea"],
            answer: "Japan"
        },
        {
            q: "Who painted the Mona Lisa?",
            options: ["Vincent van Gogh", "Pablo Picasso", "Leonardo da Vinci", "Michelangelo"],
            answer: "Leonardo da Vinci"
        },
        {
            q: "What is the largest ocean on Earth?",
            options: ["Atlantic Ocean", "Indian Ocean", "Arctic Ocean", "Pacific Ocean"],
            answer: "Pacific Ocean"
        },
        {
            q: "Which is the tallest mountain in the world?",
            options: ["K2", "Mount Everest", "Kangchenjunga", "Makalu"],
            answer: "Mount Everest"
        },
        {
            q: "How many continents are there?",
            options: ["5", "6", "7", "8"],
            answer: "7"
        }
    ],
    "History": [
        {
            q: "Who was the first President of the United States?",
            options: ["Thomas Jefferson", "George Washington", "Abraham Lincoln", "John Adams"],
            answer: "George Washington"
        },
        {
            q: "In which year did World War II end?",
            options: ["1945", "1939", "1950", "1941"],
            answer: "1945"
        },
        {
            q: "Who discovered America?",
            options: ["Christopher Columbus", "Vasco da Gama", "Ferdinand Magellan", "James Cook"],
            answer: "Christopher Columbus"
        },
        {
            q: "Which ancient civilization built the pyramids?",
            options: ["Romans", "Greeks", "Egyptians", "Mayans"],
            answer: "Egyptians"
        },
        {
            q: "When did the Titanic sink?",
            options: ["1912", "1905", "1920", "1898"],
            answer: "1912"
        },
        {
            q: "Who was the first man on the moon?",
            options: ["Buzz Aldrin", "Neil Armstrong", "Yuri Gagarin", "John Glenn"],
            answer: "Neil Armstrong"
        }
    ],
    "English Vocabulary": [
        {
            q: "What is a synonym for 'happy'?",
            options: ["Sad", "Joyful", "Angry", "Tired"],
            answer: "Joyful"
        },
        {
            q: "What does 'benevolent' mean?",
            options: ["Kind", "Evil", "Neutral", "Strange"],
            answer: "Kind"
        },
        {
            q: "What is the opposite of 'ancient'?",
            options: ["Old", "Modern", "Historic", "Classic"],
            answer: "Modern"
        },
        {
            q: "What does 'ambiguous' mean?",
            options: ["Clear", "Unclear", "Happy", "Sad"],
            answer: "Unclear"
        },
        {
            q: "What is a synonym for 'beautiful'?",
            options: ["Ugly", "Pretty", "Plain", "Ordinary"],
            answer: "Pretty"
        },
        {
            q: "What is the meaning of 'diligent'?",
            options: ["Lazy", "Hard-working", "Smart", "Funny"],
            answer: "Hard-working"
        }
    ],
    "Mixed Mode": []
};

// Create Mixed Mode by combining all subjects
for (const subject of ["Mathematics", "Science", "General Knowledge", "History", "English Vocabulary"]) {
    questionBanks["Mixed Mode"] = questionBanks["Mixed Mode"].concat(questionBanks[subject]);
}

// ===== Subject Selection Functions =====
function selectSubject(ev, subject) {
    // Remove selected class from all cards
    document.querySelectorAll('.subject-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    ev.currentTarget.classList.add('selected');
    
    selectedSubject = subject;
    document.getElementById('startGameBtn').style.display = 'block';
    
    showTempMessage(`Selected: ${subject} 📚`);
}

function startGameWithSubject() {
    if (!selectedSubject) {
        showTempMessage('Please select a subject first! 📚');
        return;
    }
    
    // Hide subject selection, show game
    document.getElementById('subjectSelection').style.display = 'none';
    document.getElementById('gameSection').style.display = 'block';
    
    // Apply difficulty settings before initializing
    applyDifficulty(selectedDifficulty);
    countdown = TIME_BY_DIFF[selectedDifficulty] || 60;

    // Initialize the game with selected subject
    initGame();
    // Start server-side session
    startServerSession();
}

// ===== Game Functions =====
function applyDifficulty(diff) {
    if (diff === 'Easy') { rows = 10; cols = 10; }
    else if (diff === 'Medium') { rows = 15; cols = 15; }
    else if (diff === 'Hard') { rows = 20; cols = 20; }
    cellSize = canvas.width / cols;
    start = {x:0, y:0};
    end = {x:rows-1, y:cols-1};
    // Set timer per difficulty
    countdown = TIME_BY_DIFF[diff] || 60;
}

// (Difficulty UI removed) Difficulty will be provided by dashboard via URL param
function getRandomQuestion(subject) {
    if (!questionBanks[subject] || questionBanks[subject].length === 0) {
        return null;
    }
    const randomIndex = Math.floor(Math.random() * questionBanks[subject].length);
    return questionBanks[subject][randomIndex];
}

function checkAnswer(question, selectedAnswer) {
    return question.answer === selectedAnswer;
}

// ===== Maze Generator =====
function generateMaze(){
    maze=[];
    for(let i=0;i<rows;i++){maze[i]=[];for(let j=0;j<cols;j++){maze[i][j]={top:true,right:true,bottom:true,left:true,visited:false};}}
    function shuffle(array){for(let i=array.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[array[i],array[j]]=[array[j],array[i]];}return array;}
    function carve(x,y){
        maze[x][y].visited=true;
        const dirs=shuffle(['up','down','left','right']);
        for(const dir of dirs){
            let nx=x,ny=y;
            if(dir==='up') nx--; else if(dir==='down') nx++; else if(dir==='left') ny--; else if(dir==='right') ny++;
            if(nx>=0&&nx<rows&&ny>=0&&ny<cols&&!maze[nx][ny].visited){
                if(dir==='up'){maze[x][y].top=false;maze[nx][ny].bottom=false;}
                if(dir==='down'){maze[x][y].bottom=false;maze[nx][ny].top=false;}
                if(dir==='left'){maze[x][y].left=false;maze[nx][ny].right=false;}
                if(dir==='right'){maze[x][y].right=false;maze[nx][ny].left=false;}
                carve(nx,ny);
            }
        }
    }
    carve(0,0);
    maze[start.x][start.y].left=false;
    maze[end.x][end.y].right=false;
}

// ===== Draw Maze =====
let hintGate = null;
function drawMaze(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const t=Date.now()/1000;
    canvas.style.background=`linear-gradient(${135+t*5}deg,#1c2a38,#0a1016)`;
    ctx.strokeStyle=WALL_COLOR; ctx.lineWidth=LINE_WIDTH;
    for(let i=0;i<rows;i++){for(let j=0;j<cols;j++){
        const x=j*cellSize,y=i*cellSize,c=maze[i][j];
        if(c.top) ctx.beginPath(),ctx.moveTo(x,y),ctx.lineTo(x+cellSize,y),ctx.stroke();
        if(c.right) ctx.beginPath(),ctx.moveTo(x+cellSize,y),ctx.lineTo(x+cellSize,y+cellSize),ctx.stroke();
        if(c.bottom) ctx.beginPath(),ctx.moveTo(x,y+cellSize),ctx.lineTo(x+cellSize,y+cellSize),ctx.stroke();
        if(c.left) ctx.beginPath(),ctx.moveTo(x,y),ctx.lineTo(x,y+cellSize),ctx.stroke();
    }}
    for(const g of gates){
        ctx.fillStyle=g.opened?"#3a506b":"orange";
        ctx.fillRect(g.y*cellSize+cellSize/4,g.x*cellSize+cellSize/4,cellSize/2,cellSize/2);
        if(g.opened) sparkles.push({x:g.y*cellSize+cellSize/2,y:g.x*cellSize+cellSize/2,r:2,alpha:1});
    }
    for(let i=sparkles.length-1;i>=0;i--){
        const s=sparkles[i];
        ctx.fillStyle=`rgba(255,255,255,${s.alpha})`;
        ctx.beginPath(); ctx.arc(s.x,s.y,s.r,0,Math.PI*2); ctx.fill();
        s.alpha-=0.02; s.r+=0.3;
        if(s.alpha<=0) sparkles.splice(i,1);
    }
    // Draw Start
    ctx.fillStyle="blue";
    ctx.fillRect(start.y*cellSize+cellSize/4,start.x*cellSize+cellSize/4,cellSize/2,cellSize/2);
    // Draw Gift
    const giftY=end.x*cellSize+cellSize/2+Math.sin(Date.now()/300)*5;
    const giftX=end.y*cellSize+cellSize/2;
    // Create a simple gift image if external image fails
    ctx.fillStyle = "#ff4d6d";
    ctx.fillRect(giftX-cellSize/3, giftY-cellSize/3, cellSize*2/3, cellSize*2/3);
    ctx.strokeStyle = "#ff8fa3";
    ctx.lineWidth = 2;
    ctx.strokeRect(giftX-cellSize/3, giftY-cellSize/3, cellSize*2/3, cellSize*2/3);
    ctx.fillStyle = "#ffccd5";
    ctx.beginPath();
    ctx.moveTo(giftX-cellSize/3, giftY);
    ctx.lineTo(giftX+cellSize/3, giftY);
    ctx.lineTo(giftX, giftY-cellSize/3);
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(giftX-cellSize/3, giftY);
    ctx.lineTo(giftX+cellSize/3, giftY);
    ctx.lineTo(giftX, giftY+cellSize/3);
    ctx.fill();
    
    // Draw Player Skin
    const playerY=player.x*cellSize+cellSize/2+Math.sin(Date.now()/200)*2;
    const playerX=player.y*cellSize+cellSize/2;
    ctx.beginPath();
    if(user.skins?.includes('skin2')) ctx.fillStyle="cyan";
    else if(user.skins?.includes('skin3')) ctx.fillStyle="red";
    else ctx.fillStyle="lime";
    ctx.arc(playerX,playerY,cellSize/4,0,Math.PI*2); ctx.fill();

    if(hintGate){ctx.strokeStyle="yellow";ctx.lineWidth=4;ctx.strokeRect(hintGate.y*cellSize+cellSize/4,hintGate.x*cellSize+cellSize/4,cellSize/2,cellSize/2);}
    requestAnimationFrame(drawMaze);
}

// ===== Generate Gates =====
function generateGates(){
    gates=[];
    
    for(let i=1;i<rows-1;i++){for(let j=1;j<cols-1;j++){
        if(Math.random()<GATE_DENSITY && !(i===start.x&&j===start.y) && !(i===end.x&&j===end.y)){
            gates.push({
                x:i,
                y:j,
                subject: selectedSubject, // Use the selected subject
                opened:false
            });
        }
    }}
}

// ===== Question Popup =====
const popup=document.getElementById('questionPopup');
const qText=document.getElementById('questionText');
const optionsDiv=document.getElementById('options');
const levelPopup=document.getElementById('levelCompletePopup');
const correctAnswerPopup=document.getElementById('correctAnswerPopup');
const shopPopup=document.getElementById('shopPopup');

// Utility: close any open popups and return focus to the canvas
function closeAllPopups() {
    popup.classList.remove('show');
    levelPopup.classList.remove('show');
    correctAnswerPopup.classList.remove('show');
    shopPopup.classList.remove('show');
    try { document.getElementById('mazeCanvas').focus(); } catch(e) {}
}

// Allow ESC to close popups
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAllPopups();
    }
});

function showQuestion(gate){
    console.log("Gate subject:", gate.subject);
    
    // Get questions from the selected subject
    const question = getRandomQuestion(gate.subject);
    
    if (!question) {
        alert("No questions available for " + gate.subject + ". Gate opened automatically.");
        gate.opened = true;
        return;
    }
    
    qText.innerText = `Subject: ${gate.subject}\n${question.q}`;
    optionsDiv.innerHTML = '';
    
    for(const opt of question.options){
        const btn = document.createElement('button');
        btn.innerText = opt;
        btn.onclick = () => {
            // Immediately close the question popup and freeze option clicks
            popup.classList.remove('show');
            // Disable all option buttons to prevent duplicate handling
            optionsDiv.querySelectorAll('button').forEach(b => b.disabled = true);
            // Clear options shortly to avoid lingering UI
            setTimeout(() => { optionsDiv.innerHTML = ''; }, 0);
            if(checkAnswer(question, opt)){
                gate.opened = true;
                
                let coinGain = 10, xpGain = 5;
                if(scoreDoublerActive){
                    coinGain *= 2;
                    xpGain *= 2;
                    scoreDoublerActive = false;
                    document.getElementById('scoreDoublerBtn').classList.remove('power-up-active');
                }
                
                // Show correct answer popup
                const rewardText = correctAnswerPopup.querySelector('.reward-amount');
                rewardText.textContent = `+${coinGain}`;
                correctAnswerPopup.classList.add('show');
                // Auto-hide the correct popup after a short delay
                setTimeout(() => { correctAnswerPopup.classList.remove('show'); }, 1200);
                
                animateReward('coins', coinGain);
                animateReward('xp', xpGain);
                user.coins += coinGain;
                user.xp += xpGain;
                _accumScore += coinGain; // track toward leaderboard/session
                user.streak++;
                user.correct_answers += 1;
                
                // Check for level up
                checkLevelUp();
                checkAchievements();
                
                flashHUD();
                saveUser();
                scheduleSync();
                // Immediate sync so server gets latest coins/xp
                syncProfileNow();
            } else {
                wrongAttempts++;
                if(wrongAttempts >= 3){
                    player = {...start};
                    wrongAttempts = 0;
                    alert("⚠️ 3 wrong attempts! Restarted.");
                } else {
                    alert("❌ Wrong answer! Try again.");
                }
                user.wrong_answers += 1;
                saveUser();
                scheduleSync();
            }
            updateHUD();
            // refocus game canvas for movement
            try { canvas.focus(); } catch(e) {}
        };
        optionsDiv.appendChild(btn);
    }
    popup.classList.add('show');
}

// ===== Check Level Up =====
function checkLevelUp() {
    const xpNeeded = user.level * 100;
    if (user.xp >= xpNeeded) {
        user.level++;
        user.xp = user.xp - xpNeeded;
        user.coins += 50; // Bonus coins for level up
        _accumScore += 50; // include bonus in session score
        showPopup(`🎉 Level Up! You are now Level ${user.level}! +50 coins`);
        checkAchievements();
        saveUser();
        scheduleSync();
        // Immediate sync on level up
        syncProfileNow();
    }
}

// ===== Check Achievements =====
function checkAchievements() {
    const newBadges = [];
    if (user.level >= 5 && !user.badges.includes("Level 5")) newBadges.push("Level 5");
    if (user.coins >= 500 && !user.badges.includes("Rich Explorer")) newBadges.push("Rich Explorer");
    if (user.streak >= 10 && !user.badges.includes("10 Day Streak")) newBadges.push("10 Day Streak");
    
    user.badges = [...new Set([...user.badges, ...newBadges])];
    newBadges.forEach(badge => showPopup(`🏅 Badge Unlocked: ${badge}!`));
}

// ===== Reward Animation =====
function animateReward(type, value){
    const el=document.querySelector(`#${type}`);
    const fly=document.createElement('div');
    fly.className='coinXP';
    fly.innerText=`+${value}`;
    el.appendChild(fly);
    setTimeout(()=>{el.removeChild(fly);},1000);
}

// ===== Check Gates =====
function checkGate(){
    if(isTimeFrozen) return;
    for(const g of gates){
        if(g.x===player.x&&g.y===player.y&&!g.opened){
            showQuestion(g);
            return;
        }
    }
    if(player.x===end.x && player.y===end.y){
        levelPopup.classList.add("show");
        animateReward('coins', 50);
        animateReward('xp', 20);
        user.coins+=50; 
        user.xp+=20; 
        _accumScore += 50; // count level completion coins toward session score
        checkLevelUp();
        checkAchievements();
        saveUser(); 
        updateHUD();
        scheduleSync();
        // Immediate sync after level complete
        syncProfileNow();
    }
}

// ===== Movement =====
document.addEventListener('keydown',e=>{
    if(isTimeFrozen) return;
    const c=maze[player.x][player.y];
    let moved=false;
    if(e.key==='ArrowUp'&&!c.top&&player.x>0){player.x--;moved=true;}
    if(e.key==='ArrowDown'&&!c.bottom&&player.x<rows-1){player.x++;moved=true;}
    if(e.key==='ArrowLeft'&&!c.left&&player.y>0){player.y--;moved=true;}
    if(e.key==='ArrowRight'&&!c.right&&player.y<cols-1){player.y++;moved=true;}
    if(moved) checkGate();
});

// ===== Save & HUD =====
function saveUser(){
    // Save to database via sync
    scheduleSync();
}
function updateHUD(){
    document.getElementById('coins').innerText=user.coins;
    document.getElementById('xp').innerText=user.xp;
    document.getElementById('streak').innerText=user.streak;
    document.getElementById('levelDisplay').innerText=user.level;
    document.getElementById('coinsHUD').innerText=user.coins;
    
    // Update power-up counts
    document.getElementById('timeFreezeCount').textContent = user.powerUps.timeFreeze || 0;
    document.getElementById('hintCount').textContent = user.powerUps.hint || 0;
    document.getElementById('skipCount').textContent = user.powerUps.skip || 0;
    document.getElementById('scoreDoublerCount').textContent = user.powerUps.scoreDoubler || 0;
    
    // Disable buttons when no power-ups left
    document.getElementById('timeFreezeBtn').disabled = !user.powerUps.timeFreeze;
    document.getElementById('hintBtn').disabled = !user.powerUps.hint;
    document.getElementById('skipBtn').disabled = !user.powerUps.skip;
    document.getElementById('scoreDoublerBtn').disabled = !user.powerUps.scoreDoubler;
    
    // Update shop display
    document.getElementById('shopCoins').textContent = user.coins;
    document.getElementById('shopTimeFreezeCount').textContent = user.powerUps.timeFreeze || 0;
    document.getElementById('shopHintCount').textContent = user.powerUps.hint || 0;
    document.getElementById('shopSkipCount').textContent = user.powerUps.skip || 0;
    document.getElementById('shopScoreDoublerCount').textContent = user.powerUps.scoreDoubler || 0;
}
function flashHUD(){const hud=document.querySelectorAll('#hud div');hud.forEach(d=>{d.classList.add('flash');setTimeout(()=>d.classList.remove('flash'),300);});}

// ===== Power-Ups =====
function usePower(type){
    if(!user.powerUps[type] || user.powerUps[type] <= 0){
        alert("No " + type + " power-ups left! Visit the shop to buy more.");
        showShop();
        return;
    }
    
    user.powerUps[type]--;
    user.powerups_used += 1;
    
    if(type==='timeFreeze'){
        isTimeFrozen=true;
        alert("⏱ Time Freeze active for 5 seconds!");
        setTimeout(()=>{
            isTimeFrozen=false;
            alert("Time Freeze ended!");
        },5000);
    }
    
    if(type==='hint'){
        hintGate=null; 
        let nearest=null,minDist=Infinity; 
        for(const g of gates){
            if(!g.opened){
                let d=Math.abs(player.x-g.x)+Math.abs(player.y-g.y); 
                if(d<minDist){
                    minDist=d;
                    nearest=g;
                }
            }
        } 
        if(nearest){
            hintGate=nearest; 
            alert("🔍 Nearest gate highlighted in yellow!");
            setTimeout(()=>{
                hintGate=null;
            },3000);
        } else {
            alert("No unopened gates nearby!");
            // Refund the power-up since no gates available
            user.powerUps[type]++;
        }
    }
    
    if(type==='skip'){
        let skipped = false;
        for(const g of gates){
            if(!g.opened){
                g.opened=true;
                skipped = true;
                flashHUD();
                alert("✅ Skip Gate used! One gate has been opened.");
                break;
            }
        }
        if(!skipped){
            alert("No gates to skip!");
            // Refund the power-up since no gates available
            user.powerUps[type]++;
        }
    }
    
    if(type==='scoreDoubler'){
        scoreDoublerActive=true;
        document.getElementById('scoreDoublerBtn').classList.add('power-up-active');
        alert("💰 Score Doubler active for your next correct answer!");
    }
    
    saveUser();
    updateHUD();
    scheduleSync();
}

// ===== Shop System =====
function showShop() {
    updateHUD();
    shopPopup.classList.add('show');
}

function hideShop() {
    shopPopup.classList.remove('show');
}

function buyPowerUp(type, price) {
    if (user.coins < price) {
        alert(`Not enough coins! You need ${price} coins but only have ${user.coins}.`);
        return;
    }
    
    user.coins -= price;
    if (!user.powerUps[type]) {
        user.powerUps[type] = 0;
    }
    user.powerUps[type]++;
    
    // Show purchase success animation
    animateReward('coins', -price);
    flashHUD();
    
    alert(`✅ Purchased 1 ${type} power-up!`);
    saveUser();
    updateHUD();
    scheduleSync();
}

function buyPowerUpPack() {
    const price = 100;
    if (user.coins < price) {
        alert(`Not enough coins! You need ${price} coins but only have ${user.coins}.`);
        return;
    }
    
    user.coins -= price;
    user.powerUps.timeFreeze = (user.powerUps.timeFreeze || 0) + 1;
    user.powerUps.hint = (user.powerUps.hint || 0) + 1;
    user.powerUps.skip = (user.powerUps.skip || 0) + 1;
    user.powerUps.scoreDoubler = (user.powerUps.scoreDoubler || 0) + 1;
    
    animateReward('coins', -price);
    flashHUD();
    
    alert("🎒 Purchased Power-Up Pack! +1 of each power-up.");
    saveUser();
    updateHUD();
    scheduleSync();
}

function buyMegaPack() {
    const price = 250;
    if (user.coins < price) {
        alert(`Not enough coins! You need ${price} coins but only have ${user.coins}.`);
        return;
    }
    
    user.coins -= price;
    user.powerUps.timeFreeze = (user.powerUps.timeFreeze || 0) + 3;
    user.powerUps.hint = (user.powerUps.hint || 0) + 3;
    user.powerUps.skip = (user.powerUps.skip || 0) + 3;
    user.powerUps.scoreDoubler = (user.powerUps.scoreDoubler || 0) + 3;
    
    animateReward('coins', -price);
    flashHUD();
    
    alert("💎 Purchased Mega Pack! +3 of each power-up.");
    saveUser();
    updateHUD();
    scheduleSync();
}

// ===== Level Complete =====
function nextLevel(){
    levelPopup.classList.remove("show");
    user.level++;
    player={...start};
    countdown = TIME_BY_DIFF[selectedDifficulty] || 60;
    generateMaze();
    generateGates();
    saveUser();
    updateHUD();
    scheduleSync();
}

function restartLevel(){
    levelPopup.classList.remove("show");
    player={...start};
    countdown = TIME_BY_DIFF[selectedDifficulty] || 60;
    updateHUD();
}

async function goToDashboard() {
    try {
        // Ensure latest coins/xp are saved server-side
        saveUser();
        await syncProfileNow();
        await endServerSession();
    } catch(e) {}
    // Small delay to allow network to flush
    setTimeout(()=>{ window.location.href = "<?= url('dashboard') ?>"; }, 200);
}

// ===== Countdown Timer =====
setInterval(()=>{
    if(!isTimeFrozen){
        countdown--;
        if(countdown<0){
            player={...start};
            countdown = TIME_BY_DIFF[selectedDifficulty] || 60;
            alert("⏳ Time over! Restarted level.");
        }
        document.getElementById('timeLeft').innerText=countdown;
    }
},1000);

// ===== Popup Function =====
function showPopup(msg){
    const popup = document.createElement('div');
    popup.className = 'popup show';
    popup.textContent = msg;
    document.body.appendChild(popup);
    setTimeout(()=>{
        popup.classList.remove('show');
        setTimeout(()=>document.body.removeChild(popup),300);
    },2000);
}

function showTempMessage(message) {
    const tempMsg = document.createElement('div');
    tempMsg.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #4CAF50;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    `;
    tempMsg.textContent = message;
    document.body.appendChild(tempMsg);
    
    setTimeout(() => {
        document.body.removeChild(tempMsg);
    }, 3000);
}

// ===== Init =====
// Initialize the game
function initGame() {
    generateMaze();
    generateGates();
    updateHUD();
    drawMaze();
    // Count a game start once per session
    if (!window._gameSessionCounted) {
        user.total_games += 1;
        saveUser();
        scheduleSync();
        window._gameSessionCounted = true;
    }
    
    // Show selected subject info
    showTempMessage(`Playing with: ${selectedSubject} 🎯`);
}

// Check if we came from dashboard with difficulty parameter and apply it
const urlParams = new URLSearchParams(window.location.search);
const diffParam = (urlParams.get('difficulty') || '').toLowerCase();
if (diffParam === 'easy' || diffParam === 'medium' || diffParam === 'hard') {
    selectedDifficulty = diffParam.charAt(0).toUpperCase() + diffParam.slice(1);
    showTempMessage(`Difficulty: ${selectedDifficulty}`);
}
// Always show subject selection so the player can choose a subject
document.getElementById('subjectSelection').style.display = 'block';
</script>
</body>
</html>
