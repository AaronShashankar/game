<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MazeQuest Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Inter',sans-serif;}
body{background:linear-gradient(to bottom right,#f4e9ff,#fff7f7);color:#222;padding:30px;transition:0.5s;}
body.dark{background:linear-gradient(to bottom right,#1c1c1c,#2a2a2a);color:#ddd;}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
header h1{font-size:28px;font-weight:700;}
header button{background:#e7ecf1;border:none;padding:8px 16px;border-radius:12px;cursor:pointer;transition:0.2s;margin-left:5px;}
header button:hover{background:#d1d8e0;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:25px;margin-bottom:30px;}
.card{background:#fff;border-radius:20px;padding:25px;box-shadow:0 8px 25px rgba(0,0,0,0.08);text-align:left;position:relative;transition:0.3s;}
body.dark .card{background:#2a2a2a;box-shadow:0 8px 25px rgba(0,0,0,0.4);color:#ddd;}
.card:hover{transform:translateY(-5px) scale(1.02);}
.card h3{margin-bottom:12px;font-size:20px;font-weight:700;}
.card p{color:#555;font-size:14px;}
body.dark .card p{color:#aaa;}
.stat-value {font-size: 32px;font-weight: 700;color: #ff6f61;margin-bottom: 5px;}
body.dark .stat-value {color: #ff8e7a;}
.stat-label {font-size: 14px;color: #777;text-transform: uppercase;letter-spacing: 1px;}
body.dark .stat-label {color: #999;}
.xp-container {margin-top: 15px;}
.xp-bar{width:100%;background:#eee;border-radius:12px;overflow:hidden;margin-top:10px;height:20px;box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);}
body.dark .xp-bar{background:#555;}
.xp-fill{height:100%;background:linear-gradient(90deg,#ffd166,#ff6f61);width:0%;transition:width 1s ease-in-out;border-radius: 12px;}
.xp-text {display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px; color: #777;}
body.dark .xp-text {color: #999;}
.avatar{width:80px;height:80px;border-radius:50%;border:3px solid #ffd166;margin-top:10px;transition:0.3s;}
.avatar:hover{transform:scale(1.1);}
.badge{display:inline-block;margin:5px;padding:8px 12px;border-radius:12px;background:gold;font-weight:600;color:#222;transition:0.3s;font-size: 12px;}
body.dark .badge{background:#ffb84d;color:#222;}
.badge-grid {display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;}
.power-up-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;}
.power-up-item {background: #f0f0f0; padding: 10px; border-radius: 8px; text-align: center;}
body.dark .power-up-item {background: #444;}
.power-up-count {font-size: 18px; font-weight: bold; color: #ff6f61;}
.power-up-label {font-size: 12px; color: #777; margin-top: 5px;}
body.dark .power-up-label {color: #999;}
.challenge-item{background:#f0f0f0;border-radius:12px;padding:12px;margin-top:8px;cursor:pointer;transition:0.3s;}
body.dark .challenge-item{background:#444;}
.challenge-item.done{text-decoration:line-through;color:green;}
#quote{font-style:italic;margin-top:12px;color:#555;font-size:16px;}
body.dark #quote{color:#ccc;}
.floating-btn{position:fixed;bottom:30px;right:30px;background:#ffd166;border:none;padding:15px;border-radius:50%;cursor:pointer;box-shadow:0 5px 15px rgba(0,0,0,0.2);transition:0.3s;font-size:20px;}
.floating-btn:hover{transform:scale(1.1);}
.progress-section {margin-top: 30px;}
.section-title {font-size: 24px; font-weight: 700; margin-bottom: 20px; color: #333;}
body.dark .section-title {color: #ddd;}
.btn {background: #ffd166; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; margin-top: 10px;}
.btn:hover {background: #ffca3a;}
.btn-full {width: 100%;}
.invite-section {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px; padding: 25px; margin-top: 40px;}
.invite-section h2 {color: white; margin-bottom: 15px; font-size: 24px;}
.invite-stats {display: flex; justify-content: space-around; text-align: center; margin: 20px 0;}
.invite-stat {padding: 15px;}
.invite-stat-value {font-size: 32px; font-weight: 700; margin-bottom: 5px;}
.invite-stat-label {font-size: 14px; opacity: 0.9;}
.referral-code {background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; margin: 15px 0; text-align: center;}
.referral-code input {width: 100%; padding: 12px; border: none; border-radius: 8px; text-align: center; font-weight: 600; font-size: 16px; margin-bottom: 10px;}
.modal {display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;}
.modal-content {position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 90%; max-height: 90%; overflow-y: auto;}
body.dark .modal-content {background: #333; color: white;}
.close-modal {position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer;}
.reward-popup {position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: gold; color: #333; padding: 20px 40px; border-radius: 15px; font-weight: 700; font-size: 24px; z-index: 1001; box-shadow: 0 10px 30px rgba(0,0,0,0.3); animation: rewardBounce 1s ease-in-out;}
@keyframes rewardBounce {0% { transform: translate(-50%, -50%) scale(0); }50% { transform: translate(-50%, -50%) scale(1.2); }100% { transform: translate(-50%, -50%) scale(1); }}
</style>
  <?= app_scripts() ?>
</head>
<body>

<header>
<div>
  <h1>👤 My Profile</h1>
  <div style="font-size:14px;color:#666;margin-top:4px;">
    <span id="headerUserName" style="font-weight:700;">Username</span>
    <span style="opacity:0.7;"> • </span>
    <span id="headerUserEmail">user@example.com</span>
  </div>
</div>
<div>
<button class="darkmode-btn" onclick="toggleDarkMode()">🌙</button>
<button class="mute-btn" onclick="toggleMusic()">🔊</button>
<button onclick="goDashboard()">🏠 Dashboard</button>
</div>
</header>

<div class="grid">

<!-- User Info -->
<div class="card">
<h3>📝 User Info</h3>
<label>Username:</label>
<input type="text" id="username" placeholder="Enter username" style="width:100%;padding:8px;margin:5px 0;border-radius:8px;border:1px solid #ccc;">
<label>Email:</label>
<input type="email" id="email" placeholder="Enter email" style="width:100%;padding:8px;margin:5px 0;border-radius:8px;border:1px solid #ccc;">
<label>New Password:</label>
<input type="password" id="password" placeholder="New password (leave blank to keep)" style="width:100%;padding:8px;margin:5px 0;border-radius:8px;border:1px solid #ccc;">
<button class="btn btn-full" onclick="saveProfile()">💾 Save Profile</button>
</div>

<!-- Avatar -->
<div class="card">
<h3>👤 Avatar</h3>
<img src="" alt="Your avatar" id="avatar" class="avatar">
<button class="btn btn-full" onclick="changeAvatar()">🔄 Change Avatar</button>
</div>

<!-- Coins -->
<div class="card">
<h3>💰 Coins</h3>
<div class="stat-value" id="coins">0</div>
<div class="stat-label">Total Coins</div>
</div>

<!-- Level & XP -->
<div class="card">
<h3>🎯 Level & XP</h3>
<div class="stat-value" id="level">Level 1</div>
<div class="xp-container">
<div class="xp-text">
<span>XP: <span id="currentXP">0</span>/<span id="nextLevelXP">100</span></span>
<span id="xpPercent">0%</span>
</div>
<div class="xp-bar"><div class="xp-fill" id="xpFill"></div></div>
</div>
</div>

<!-- Streak -->
<div class="card">
<h3>🔥 Streak</h3>
<div class="stat-value" id="streak">0</div>
<div class="stat-label">Days</div>
</div>

<!-- Power-ups -->
<div class="card">
<h3>⚡ Power-ups</h3>
<div class="power-up-grid">
<div class="power-up-item">
<div class="power-up-count" id="timeFreezeCount">0</div>
<div class="power-up-label">⏱️ Time Freeze</div>
</div>
<div class="power-up-item">
<div class="power-up-count" id="hintCount">0</div>
<div class="power-up-label">🔍 Hint Key</div>
</div>
<div class="power-up-item">
<div class="power-up-count" id="skipCount">0</div>
<div class="power-up-label">⏩ Skip Gate</div>
</div>
<div class="power-up-item">
<div class="power-up-count" id="scoreDoublerCount">0</div>
<div class="power-up-label">💰 Score Doubler</div>
</div>
</div>
</div>

<!-- Badges -->
<div class="card">
<h3>🏅 Badges</h3>
<div id="badges" class="badge-grid"></div>
<div id="noBadges" style="color: #777; font-style: italic; margin-top: 10px;">No badges yet. Keep playing to earn badges!</div>
</div>

</div>

<!-- Referral Section -->
<div class="invite-section">
<h2>👥 Invite Friends & Earn 500 Coins! 🎁</h2>
<p>Share your referral code and get 500 coins when friends join and play their first game!</p>
<div class="invite-stats">
<div class="invite-stat">
<div class="invite-stat-value" id="friendsInvited">0</div>
<div class="invite-stat-label">Friends Invited</div>
</div>
<div class="invite-stat">
<div class="invite-stat-value" id="coinsEarned">0</div>
<div class="invite-stat-label">Coins Earned</div>
</div>
<div class="invite-stat">
<div class="invite-stat-value" id="pendingRewards">0</div>
<div class="invite-stat-label">Pending Rewards</div>
</div>
</div>
<div class="referral-code">
<h3>Your Referral Code</h3>
<input type="text" id="referralCodeInput" readonly value="Loading...">
<button class="btn btn-full btn-share" onclick="copyReferralCode()">📋 Copy Code</button>
</div>
</div>

<div id="rewardPopup" class="reward-popup" style="display: none;">
🎉 +500 Coins! 🎉
</div>

<?= page_script('profile') ?>
</body>
</html>
