<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MazeQuest Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    /* --- General Reset & Fonts --- */
    * { box-sizing: border-box; margin:0; padding:0; font-family: 'Inter', sans-serif; }
    body { 
      background: #f5f5f5; 
      color: #222; 
      padding: 20px; 
      transition: background 0.5s, color 0.5s; 
      overflow-x: hidden; 
    }
    body.dark { 
      background: #1a1a1a; 
      color: #ddd; 
    }

    /* --- Header --- */
    header { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      margin-bottom: 30px; 
      padding: 15px 0;
    }
    header h1 { 
      font-size: 32px; 
      font-weight: 700; 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      color: #4a4a4a;
    }
    body.dark header h1 {
      color: #f0f0f0;
    }
    .header-buttons {
      display: flex;
      gap: 10px;
    }
    header button { 
      background: #e7ecf1; 
      border: none; 
      padding: 8px 16px; 
      border-radius: 20px; 
      cursor: pointer; 
      transition: 0.3s; 
      font-weight: 600;
    }
    body.dark header button {
      background: #333;
      color: #ddd;
    }
    header button:hover { 
      transform: scale(1.05); 
      background: #d0d8e0;
    }
    body.dark header button:hover {
      background: #444;
    }

    /* --- Main Layout --- */
    .main-container {
      display: flex;
      gap: 20px;
    }
    
    /* --- Sidebar --- */
    .sidebar {
      width: 250px;
      flex-shrink: 0;
    }
    
    .profile-card {
      background: #fff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      text-align: center;
      margin-bottom: 20px;
      transition: 0.3s;
      position: relative;
    }
    body.dark .profile-card {
      background: #2a2a2a;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .profile-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .profile-pic-container {
      position: relative;
      width: 100px;
      height: 100px;
      margin: 0 auto 15px;
    }
    
    .profile-pic {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      border: 3px solid #ffd166;
      object-fit: cover;
      transition: 0.3s;
    }
    .profile-pic:hover {
      transform: scale(1.05);
    }
    
    .upload-btn {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #ff6f61;
      color: white;
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      cursor: pointer;
      font-size: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: 0.3s;
    }
    .upload-btn:hover {
      transform: scale(1.1);
      background: #ff4a3a;
    }
    
    .profile-card h3 {
      margin-bottom: 5px;
      font-size: 18px;
      font-weight: 700;
    }
    .profile-card p {
      font-size: 14px;
      color: #666;
      margin-bottom: 15px;
    }
    body.dark .profile-card p {
      color: #aaa;
    }
    
    .avatar-btn { 
      background: #ffd166; 
      border: none; 
      border-radius: 12px; 
      padding: 10px 18px; 
      font-weight: 600; 
      cursor: pointer; 
      transition: 0.3s; 
      width: 100%; 
    }
    .avatar-btn:hover { 
      background: #ffca3a; 
      transform: scale(1.05); 
    }
    
    /* --- Navigation --- */
    .nav-section {
      background: #fff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      margin-bottom: 20px;
      transition: 0.3s;
    }
    body.dark .nav-section {
      background: #2a2a2a;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .nav-section h3 {
      margin-bottom: 15px;
      font-size: 18px;
      font-weight: 700;
      color: #4a4a4a;
    }
    body.dark .nav-section h3 {
      color: #f0f0f0;
    }
    
    .nav-links {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .nav-links a { 
      color: #ff6f61; 
      text-decoration: none; 
      font-weight: 600; 
      transition: 0.3s; 
      padding: 8px 12px;
      border-radius: 8px;
    }
    .nav-links a:hover { 
      background: rgba(255, 111, 97, 0.1);
      transform: translateX(5px);
    }
    
    /* --- Content Area --- */
    .content {
      flex: 1;
    }
    
    /* --- Grid Layout --- */
    .grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
      gap: 20px; 
      margin-bottom: 30px; 
    }
    .card { 
      background: #fff; 
      border-radius: 16px; 
      padding: 20px; 
      box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
      text-align: left; 
      position: relative; 
      transition: 0.3s, transform 0.3s; 
    }
    body.dark .card { 
      background: #2a2a2a; 
      box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
      color: #ddd; 
    }
    .card:hover { 
      transform: translateY(-5px); 
      box-shadow: 0 8px 20px rgba(0,0,0,0.12); 
    }
    .card h3 { 
      margin-bottom: 10px; 
      font-size: 18px; 
      font-weight: 600; 
      color: #4a4a4a;
    }
    body.dark .card h3 {
      color: #f0f0f0;
    }
    .card p { 
      font-size: 14px; 
      color: #666; 
    }
    body.dark .card p { 
      color: #aaa; 
    }

    /* --- Coin Sticker --- */
    .coin-sticker {
      position: absolute;
      top: -15px;
      right: -15px;
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #ffd166, #ffb347);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 18px;
      color: #8a6700;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      border: 3px solid #ffca3a;
      animation: coinGlow 2s infinite alternate;
      z-index: 10;
    }
    
    @keyframes coinGlow {
      0% { box-shadow: 0 4px 12px rgba(255, 209, 102, 0.5); }
      100% { box-shadow: 0 4px 20px rgba(255, 209, 102, 0.8), 0 0 30px rgba(255, 209, 102, 0.4); }
    }
    
    .coin-sticker::after {
      content: "🪙";
      font-size: 24px;
      margin-left: 2px;
    }

    /* --- XP Progress Bar --- */
    .xp-bar { 
      width: 100%; 
      background: #eee; 
      border-radius: 12px; 
      height: 18px; 
      overflow: hidden; 
      margin-top: 5px; 
    }
    body.dark .xp-bar { 
      background: #555; 
    }
    .xp-fill { 
      height: 100%; 
      background: linear-gradient(90deg, #ffd166, #ff6f61); 
      width: 0%; 
      transition: 0.5s ease;
      box-shadow: 0 0 10px #ffd166, 0 0 20px #ff6f61; 
      animation: glowXP 2s ease-in-out infinite alternate;
    }
    @keyframes glowXP { 
      0% { box-shadow: 0 0 5px #ffd166, 0 0 10px #ff6f61; } 
      100% { box-shadow: 0 0 15px #ffd166, 0 0 30px #ff6f61; } 
    }

    /* --- Buttons --- */
    .bonus-btn, .spin-btn, .darkmode-btn, .mute-btn { 
      background: #ffd166; 
      border: none; 
      border-radius: 12px; 
      padding: 10px 18px; 
      font-weight: 600; 
      cursor: pointer; 
      transition: 0.3s; 
      width: 100%; 
      margin-top: 10px; 
    }
    .bonus-btn:hover, .spin-btn:hover, .darkmode-btn:hover, .mute-btn:hover { 
      background: #ffca3a; 
      transform: scale(1.05); 
    }
    .bonus-btn:disabled, .spin-btn:disabled { 
      background: #ccc; 
      cursor: not-allowed; 
    }

    /* --- Difficulty Cards --- */
    .section-title {
      margin: 30px 0 20px;
      font-size: 24px;
      font-weight: 700;
      color: #4a4a4a;
    }
    body.dark .section-title {
      color: #f0f0f0;
    }
    
    .difficulty-grid { 
      display: flex; 
      flex-wrap: wrap; 
      gap: 20px; 
      margin-bottom: 30px;
    }
    .diff-card { 
      flex: 1 1 250px; 
      border-radius: 16px; 
      border: 2px solid #eee; 
      padding: 20px; 
      cursor: pointer; 
      transition: 0.3s; 
      position: relative; 
      overflow: hidden; 
      background: #fff;
    }
    body.dark .diff-card {
      background: #2a2a2a;
      border-color: #444;
    }
    .diff-card:hover { 
      transform: scale(1.03); 
      box-shadow: 0 8px 20px rgba(0,0,0,0.12); 
    }
    .easy { border-color: #b4f3b2; } 
    .medium { border-color: #ffd6a5; } 
    .hard { border-color: #ffb3b3; }
    .easy h4 { color: #00a000; } 
    .medium h4 { color: #ff7b00; } 
    .hard h4 { color: #ff3b3b; }
    
    .start {
      margin-top: 15px;
      font-weight: 600;
      color: #ff6f61;
    }

    /* --- Spin Wheel Modal --- */
    .modal { 
      display: none; 
      position: fixed; 
      top: 0; 
      left: 0; 
      width: 100%; 
      height: 100%; 
      background: rgba(0,0,0,0.85); 
      z-index: 1000; 
    }
    .modal-content { 
      position: absolute; 
      top: 50%; 
      left: 50%; 
      transform: translate(-50%, -50%); 
      background: white; 
      padding: 30px; 
      border-radius: 20px; 
      text-align: center; 
      max-width: 90%; 
      max-height: 90%; 
      overflow-y: auto; 
      transition: 0.3s; 
    }
    body.dark .modal-content { 
      background: #222; 
      color: #fff; 
    }
    .close-modal { 
      position: absolute; 
      top: 10px; 
      right: 15px; 
      font-size: 24px; 
      cursor: pointer; 
    }
    .wheel-container { 
      position: relative; 
      width: 300px; 
      height: 300px; 
      margin: 0 auto; 
    }
    .wheel { 
      width: 100%; 
      height: 100%; 
      border-radius: 50%; 
      /* "from 90deg" lines the slices up with the labels (which start at 3 o'clock). */
      background: conic-gradient(from 90deg,
        #ff6b6b 0deg 60deg, #4ecdc4 60deg 120deg, #45b7d1 120deg 180deg,
        #96ceb4 180deg 240deg, #feca57 240deg 300deg, #ff9ff3 300deg 360deg
      ); 
      transition: transform 4s cubic-bezier(0.2, 0.8, 0.3, 1); 
    }
    .wheel-pointer { 
      position: absolute; 
      top: -20px; 
      left: 50%; 
      transform: translateX(-50%); 
      width: 40px; 
      height: 40px; 
      background: #ffd166; 
      clip-path: polygon(50% 100%, 0 0, 100% 0); 
      z-index: 10; 
    }
    .wheel-item { 
      position: absolute; 
      top: 50%; 
      left: 50%; 
      transform-origin: left center; 
      width: 50%; 
      text-align: center; 
      color: white; 
      font-weight: bold; 
      text-shadow: 1px 1px 2px rgba(0,0,0,0.5); 
    }
    .spin-btn-wheel { 
      background: linear-gradient(45deg, #ff6b6b, #ffd166); 
      border: none; 
      border-radius: 25px; 
      padding: 15px 30px; 
      font-size: 18px; 
      font-weight: bold; 
      color: white; 
      cursor: pointer; 
      margin-top: 20px; 
      transition: 0.3s; 
    }
    .spin-btn-wheel:hover { 
      transform: scale(1.05); 
      box-shadow: 0 5px 15px rgba(255,107,107,0.4); 
    }
    .spin-result { 
      margin-top: 20px; 
      padding: 15px; 
      border-radius: 15px; 
      background: rgba(255,255,255,0.15); 
      font-weight: bold;
      box-shadow: 0 0 10px #ff6f61, 0 0 20px #ffd166; 
      animation: glowResult 2s ease-in-out infinite alternate;
    }
    @keyframes glowResult { 
      0% { box-shadow: 0 0 5px #ff6f61, 0 0 10px #ffd166; } 
      100% { box-shadow: 0 0 15px #ff6f61, 0 0 30px #ffd166; } 
    }

    /* --- Popup --- */
    .popup { 
      position: fixed; 
      top: 50%; 
      left: 50%; 
      transform: translate(-50%, -50%) scale(0); 
      background: #fff; 
      padding: 20px 30px; 
      border-radius: 16px; 
      box-shadow: 0 5px 20px rgba(0,0,0,0.2); 
      font-size: 18px; 
      font-weight: 600; 
      z-index: 999; 
      transition: transform 0.3s ease; 
    }
    .popup.show { 
      transform: translate(-50%, -50%) scale(1); 
    }
    body.dark .popup { 
      background: #222; 
      color: #fff; 
    }

    /* --- Quote of the Day --- */
    #quote { 
      font-style: italic; 
      margin-top: 10px; 
      color: #555; 
    }
    body.dark #quote { 
      color: #ccc; 
    }

    /* --- Weekly Challenge --- */
    .challenge { 
      background: #f0f0f0; 
      border-radius: 12px; 
      padding: 15px; 
      margin-top: 10px; 
    }
    body.dark .challenge { 
      background: #444; 
    }

    /* --- Footer --- */
    footer { 
      margin-top: 40px; 
      text-align: center; 
      font-size: 14px; 
      color: #777; 
      transition: color 0.3s; 
    }
    body.dark footer { 
      color: #aaa; 
    }

    /* --- Profile Modal --- */
    #profilePanel .modal-content {
      width: 400px;
      max-width: 90%;
    }
    #profilePanel label {
      display: block;
      margin: 15px 0 5px;
      font-weight: 600;
    }
    #profilePanel input {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #ddd;
      margin-bottom: 10px;
    }
    body.dark #profilePanel input {
      background: #333;
      border-color: #555;
      color: #ddd;
    }
    
    /* --- File Upload --- */
    .file-input {
      display: none;
    }
    
    /* --- Leaderboard --- */
    .lb-btn {
      background: #f0f0f0;
      border: 2px solid #ddd;
      padding: 8px 16px;
      margin: 5px;
      border-radius: 20px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: 0.3s;
    }
    body.dark .lb-btn {
      background: #333;
      border-color: #555;
      color: #ddd;
    }
    .lb-btn:hover {
      transform: scale(1.05);
      background: #ff6f61;
      border-color: #ff6f61;
      color: white;
    }
    .lb-btn.active {
      background: #ff6f61;
      border-color: #ff6f61;
      color: white;
    }
    
    .lb-item {
      display: flex;
      align-items: center;
      padding: 12px;
      margin: 8px 0;
      background: #f9f9f9;
      border-radius: 12px;
      transition: 0.3s;
    }
    body.dark .lb-item {
      background: #333;
    }
    .lb-item:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .lb-rank {
      font-size: 20px;
      font-weight: 700;
      width: 40px;
      text-align: center;
      color: #ff6f61;
    }
    .lb-rank.top1 { color: #FFD700; }
    .lb-rank.top2 { color: #C0C0C0; }
    .lb-rank.top3 { color: #CD7F32; }
    
    .lb-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin: 0 12px;
      border: 2px solid #ffd166;
    }
    
    .lb-info {
      flex: 1;
    }
    .lb-username {
      font-weight: 600;
      font-size: 16px;
      margin-bottom: 3px;
    }
    .lb-stats {
      font-size: 12px;
      color: #666;
    }
    body.dark .lb-stats {
      color: #aaa;
    }
    
    .lb-value {
      font-size: 18px;
      font-weight: 700;
      color: #ff6f61;
      margin-left: auto;
    }
  </style>
  <?= app_scripts() ?>
</head>
<body>
  <header>
    <h1>🎮 MazeQuest</h1>
    <div class="header-buttons">
      <button class="darkmode-btn" onclick="toggleDarkMode()">🌙 Dark Mode</button>
      <button class="mute-btn" onclick="toggleMusic()">🔊 Mute</button>
      <button onclick="logout()">Logout</button>
    </div>
  </header>

  <div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Profile Card -->
      <div class="profile-card">
        <div class="profile-pic-container">
          <img src="" alt="" id="profileAvatar" class="profile-pic">
          <button class="upload-btn" onclick="document.getElementById('fileInput').click()">+</button>
          <input type="file" id="fileInput" class="file-input" accept="image/*" onchange="handleImageUpload(event)">
        </div>
        <div>
          <h3 id="profileUsername"></h3>
          <p id="profileEmail"></p>
          <button class="avatar-btn" onclick="openProfilePanel()">Edit Profile</button>
        </div>
      </div>

      <!-- Navigation -->
      <div class="nav-section">
        <h3>🔗 Quick Links</h3>
        <div class="nav-links">
          <a href="<?= url('profile') ?>">👤 Profile</a>
          <a href="<?= url('game') ?>">🎮 Play Now</a>
          <a href="<?= url('leaderboard') ?>">🏆 Leaderboard</a>
          <a href="#" onclick="openSpinWheel()">🎡 Spin Wheel</a>
        </div>
      </div>

      <!-- Quote of the Day -->
      <div class="nav-section">
        <h3>📜 Quote of the Day</h3>
        <p id="quote">"The only way to solve a maze is to keep moving."</p>
      </div>
    </div>

    <!-- Content Area -->
    <div class="content">
      <!-- Main Dashboard Grid -->
      <div class="grid">
        <div class="card">
          <div class="coin-sticker" id="coinSticker">100</div>
          <h3 id="coins">100</h3>
          <p>Coins</p>
          <button class="bonus-btn" id="bonusBtn" onclick="claimBonus()">Claim Daily Bonus 🎁</button>
          <button class="spin-btn" id="spinBtn" onclick="openSpinWheel()">Spin & Win 🎡</button>
        </div>
        <div class="card">
          <h3 id="level">Level 1</h3>
          <p>XP: <span id="xp">0</span></p>
          <div class="xp-bar"><div class="xp-fill" id="xpFill"></div></div>
        </div>
        <div class="card">
          <h3 id="streak">0</h3>
          <p>Check-in Streak (days)</p>
        </div>
        <div class="card">
          <h3>🏅 Achievements</h3>
          <p id="badges">No badges yet</p>
        </div>
        <div class="card">
          <h3>🏆 Weekly Challenge</h3>
          <div id="challengeList" class="challenge">Solve 3 mazes in Medium difficulty this week</div>
        </div>
        <div class="card">
          <h3>📊 Progress Chart</h3>
          <canvas id="progressChart"></canvas>
        </div>
      </div>

      <!-- Choose Difficulty -->
      <section>
        <h2 class="section-title">Choose Difficulty</h2>
        <div class="difficulty-grid">
          <div class="diff-card easy" onclick="startGame('easy')">
            <h4>😊 Easy</h4>
            <p>Small maze, simple questions</p>
            <div class="start">Start Game →</div>
          </div>
          <div class="diff-card medium" onclick="startGame('medium')">
            <h4>⚡ Medium</h4>
            <p>Bigger maze with timer</p>
            <div class="start">Start Game →</div>
          </div>
          <div class="diff-card hard" onclick="startGame('hard')">
            <h4>🔥 Hard</h4>
            <p>Large maze, fog of war</p>
            <div class="start">Start Game →</div>
          </div>
        </div>
      </section>

      <!-- Leaderboard -->
      <div class="card" style="margin-top: 30px;">
        <h3>🏆 Top Players</h3>
        <div style="margin-bottom: 15px;">
          <button onclick="loadLeaderboard('xp')" class="lb-btn" id="xpBtn">🎯 XP</button>
          <button onclick="loadLeaderboard('coins')" class="lb-btn" id="coinsBtn">🪙 Coins</button>
          <button onclick="loadLeaderboard('level')" class="lb-btn" id="levelBtn">⭐ Level</button>
          <button onclick="loadLeaderboard('streak')" class="lb-btn" id="streakBtn">🔥 Streak</button>
        </div>
        <div id="leaderboardContent">
          <p style="text-align: center; color: #999;">Loading...</p>
        </div>
      </div>
    </div>
  </div>

  <footer>© 2025 MazeQuest — Learn & Play</footer>

  <!-- Spin Wheel Modal -->
  <div id="spinModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeSpinWheel()">&times;</span>
      <h2>🎡 Lucky Spin Wheel</h2>
      <p class="spins-left">Spins left today: <span id="spinsLeft">3</span></p>
      <div class="wheel-container">
        <div class="wheel-pointer"></div>
        <div class="wheel" id="wheel">
          <div class="wheel-item" style="transform: rotate(30deg) translateX(50%);">10 Coins</div>
          <div class="wheel-item" style="transform: rotate(90deg) translateX(50%);">Time Freeze</div>
          <div class="wheel-item" style="transform: rotate(150deg) translateX(50%);">25 Coins</div>
          <div class="wheel-item" style="transform: rotate(210deg) translateX(50%);">Hint Key</div>
          <div class="wheel-item" style="transform: rotate(270deg) translateX(50%);">50 Coins</div>
          <div class="wheel-item" style="transform: rotate(330deg) translateX(50%);">Skip Gate</div>
        </div>
      </div>
      <button class="spin-btn-wheel" id="spinWheelBtn" onclick="spinWheel()">SPIN! (Cost: 5 coins)</button>
      <div class="spin-result" id="spinResult">Spin the wheel to win amazing rewards!</div>
    </div>
  </div>

  <!-- Popup -->
  <div class="popup" id="popup"></div>

  <!-- Profile Modal -->
  <div id="profilePanel" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeProfilePanel()">&times;</span>
      <h2>👤 Edit Profile</h2>
      <div class="profile-pic-container">
        <img src="" alt="" id="panelAvatar" class="profile-pic">
        <button class="upload-btn" onclick="document.getElementById('panelFileInput').click()">+</button>
        <input type="file" id="panelFileInput" class="file-input" accept="image/*" onchange="handlePanelImageUpload(event)">
      </div>
      <label>Username</label>
      <input type="text" id="panelUsername" placeholder="Enter username">
      <label>Email</label>
      <input type="email" id="panelEmail" placeholder="Enter email">
      <label>Current Password</label>
      <input type="password" id="panelCurrentPassword" placeholder="Current password">
      <label>New Password</label>
      <input type="password" id="panelNewPassword" placeholder="New password">
      <button class="bonus-btn" onclick="saveProfilePanel()">Save Changes 💾</button>
    </div>
  </div>

  <audio id="bgMusic" src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" loop preload="none"></audio>

  <?= page_script('dashboard') ?>
</body>
</html>
