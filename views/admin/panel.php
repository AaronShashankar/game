<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - MazeQuest</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a3a, #0a1016);
            color: #fff;
            min-height: 100vh;
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .admin-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
        }
        
        .admin-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-primary {
            background: #6b46ff;
            color: white;
        }
        
        .btn-danger {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #ffd166;
        }
        
        .stat-label {
            font-size: 14px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .admin-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #ffd166;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .users-table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 600;
            color: #ffd166;
        }
        
        .users-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: #1a2a3a;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #ffd166;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: none;
            color: #aaa;
            cursor: pointer;
            border-radius: 6px;
            transition: 0.3s;
        }
        
        .tab-btn.active {
            background: rgba(107, 70, 255, 0.2);
            color: #6b46ff;
            border: 1px solid #6b46ff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            background: #444;
        }
        
        .strength-weak { background: #ff6b6b; width: 25%; }
        .strength-medium { background: #ffd166; width: 50%; }
        .strength-strong { background: #51cf66; width: 75%; }
        .strength-very-strong { background: #2b8a3e; width: 100%; }
        
        .system-health {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .health-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .health-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            margin-top: 5px;
            display: inline-block;
        }
        
        .status-online { background: #51cf66; color: white; }
        .status-offline { background: #ff6b6b; color: white; }
        .status-warning { background: #ffd166; color: #212529; }
        
        .log-entry {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 12px;
            border-left: 3px solid #6b46ff;
        }
        
        .log-error { border-left-color: #ff6b6b; }
        .log-warning { border-left-color: #ffd166; }
        .log-success { border-left-color: #51cf66; }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .quick-stat {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .quick-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #ffd166;
        }
        
        .quick-stat-label {
            font-size: 12px;
            color: #aaa;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #aaa;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6b46ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
  <?= app_scripts() ?>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <nav class="admin-nav">
            <div class="admin-title">
                <span>🔐</span>
                MazeQuest Admin Panel
            </div>
            <div class="admin-actions">
                <button class="btn btn-secondary" onclick="refreshData()">🔄 Refresh</button>
                <button class="btn btn-primary" onclick="openAddUserModal()">➕ Add User</button>
                <button class="btn btn-danger" onclick="logout()">🚪 Logout</button>
            </div>
        </nav>
    </header>

    <!-- Admin Content -->
    <div class="admin-container">
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('dashboard')">📊 Dashboard</button>
            <button class="tab-btn" onclick="switchTab('users')">👥 Users</button>
            <button class="tab-btn" onclick="switchTab('security')">🔒 Security</button>
            <button class="tab-btn" onclick="switchTab('system')">⚙️ System</button>
            <button class="tab-btn" onclick="switchTab('analytics')">📈 Analytics</button>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-content active">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalUsers">0</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalGames">0</div>
                    <div class="stat-label">Games Played</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalCoins">0</div>
                    <div class="stat-label">Total Coins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="activeUsers">0</div>
                    <div class="stat-label">Active Today</div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="admin-section">
                <h2 class="section-title">🚀 Quick Overview</h2>
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-value" id="avgLevel">0</div>
                        <div class="quick-stat-label">Avg Level</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value" id="totalXP">0</div>
                        <div class="quick-stat-label">Total XP</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value" id="gatesOpened">0</div>
                        <div class="quick-stat-label">Gates Opened</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value" id="correctAnswers">0</div>
                        <div class="quick-stat-label">Correct Answers</div>
                    </div>
                </div>
            </div>

            <!-- Game Appearance (Maze View) -->
            <div class="admin-section">
                <h2 class="section-title">🎨 Game Appearance (Maze View)</h2>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:16px;">
                    <div class="form-group">
                        <label>Maze Wall Color</label>
                        <input type="color" id="mazeWallColor" value="#5bc0be">
                    </div>
                    <div class="form-group">
                        <label>Gate Density (0.02–0.15)</label>
                        <input type="range" id="gateDensity" min="0.02" max="0.15" step="0.01" value="0.07" oninput="document.getElementById('gateDensityVal').textContent=this.value">
                        <div style="margin-top:6px; color:#aaa;">Current: <span id="gateDensityVal">0.07</span></div>
                    </div>
                    <div class="form-group">
                        <label>Wall Line Width (1–8)</label>
                        <input type="range" id="wallLineWidth" min="1" max="8" step="1" value="4" oninput="document.getElementById('wallLineWidthVal').textContent=this.value">
                        <div style="margin-top:6px; color:#aaa;">Current: <span id="wallLineWidthVal">4</span> px</div>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-success" onclick="saveMazeViewSettings()">💾 Save Appearance</button>
                    <button class="btn btn-secondary" onclick="resetMazeViewSettings()">↩️ Reset Defaults</button>
                </div>
                <small style="color:#aaa;">These settings affect how the maze is drawn for everyone on this device.</small>
            </div>

            <!-- Recent Activity -->
            <div class="admin-section">
                <h2 class="section-title">📝 Recent Activity</h2>
                <div id="recentActivity">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Loading recent activity...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Management Tab -->
        <div id="users-tab" class="tab-content">
            <div class="admin-section">
                <h2 class="section-title">👥 User Management</h2>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Level</th>
                                <th>Coins</th>
                                <th>XP</th>
                                <th>Streak</th>
                                <th>Games</th>
                                <th>PowerUps</th>
                                <th>Correct</th>
                                <th>Wrong</th>
                                <th>Last Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="loading">
                                    <div class="spinner"></div>
                                    <p>Loading users...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="admin-section">
                <h2 class="section-title">🔐 Password Management</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h3 style="margin-bottom: 15px; color: #ffd166;">Change Admin Password</h3>
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" id="currentPassword">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" id="newAdminPassword" onkeyup="checkPasswordStrength()">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" id="confirmPassword">
                        </div>
                        <button class="btn btn-primary" onclick="changeAdminPassword()">Update Password</button>
                    </div>
                    <div>
                        <h3 style="margin-bottom: 15px; color: #ffd166;">Security</h3>
                        <p style="color:#ccc;line-height:1.6;">
                          Failed logins are rate-limited (10 per IP per 15 minutes) and every login and
                          rejected game event is recorded in the security log below.
                          Two-factor authentication and custom session timeouts are not implemented.
                        </p>
                    </div>
                </div>
            </div>

            <div class="admin-section">
                <h2 class="section-title">📋 Security Logs</h2>
                <div id="securityLogs" style="max-height: 300px; overflow-y: auto;">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Loading security logs...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Tab -->
        <div id="system-tab" class="tab-content">
            <div class="admin-section">
                <h2 class="section-title">⚙️ System Controls</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                    <button class="btn btn-primary" onclick="backupData()">💾 Backup Data</button>
                    <button class="btn btn-secondary" onclick="exportData()">📤 Export Data</button>
                </div>
            </div>

            <div class="admin-section">
                <h2 class="section-title">🖥️ System Health</h2>
                <div class="system-health">
                    <div class="health-item">
                        <div>Database</div>
                        <div class="health-status status-online" id="dbStatus">Online</div>
                    </div>
                    <div class="health-item">
                        <div>Storage</div>
                        <div class="health-status status-online" id="storageStatus">Healthy</div>
                    </div>
                    <div class="health-item">
                        <div>Memory</div>
                        <div class="health-status status-online" id="memoryStatus">Optimal</div>
                    </div>
                    <div class="health-item">
                        <div>Security</div>
                        <div class="health-status status-online" id="securityStatus">Protected</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics-tab" class="tab-content">
            <div class="admin-section">
                <h2 class="section-title">📈 Game Analytics</h2>
                <div id="gameStats">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h3 style="color: #ffd166; margin-bottom: 15px;">Performance Metrics</h3>
                            <div class="form-group">
                                <label>Average Game Duration</label>
                                <input type="text" id="avgGameDuration" readonly>
                            </div>
                            <div class="form-group">
                                <label>Success Rate</label>
                                <input type="text" id="successRate" readonly>
                            </div>
                            <div class="form-group">
                                <label>Most Popular Subject</label>
                                <input type="text" id="popularSubject" readonly>
                            </div>
                        </div>
                        <div>
                            <h3 style="color: #ffd166; margin-bottom: 15px;">User Engagement</h3>
                            <div class="form-group">
                                <label>Daily Active Users</label>
                                <input type="text" id="dailyActiveUsers" readonly>
                            </div>
                            <div class="form-group">
                                <label>Run Completion Rate</label>
                                <input type="text" id="retentionRate" readonly>
                            </div>
                            <div class="form-group">
                                <label>Hardest Question</label>
                                <input type="text" id="avgSessionTime" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddUserModal()" style="float: right; cursor: pointer; font-size: 24px;">&times;</span>
            <h3 class="modal-title">➕ Add New User</h3>
            <form id="addUserForm">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="newUsername" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="newUserPassword" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="newEmail" required>
                </div>
                <div class="form-group">
                    <label>Initial Coins</label>
                    <input type="number" id="initialCoins" value="100" min="0">
                </div>
                <div class="form-group">
                    <label>Initial Level</label>
                    <input type="number" id="initialLevel" value="1" min="1" max="10">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <?= page_script('admin-panel') ?>
</body>
</html>
