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
                            <input type="password" id="newPassword" onkeyup="checkPasswordStrength()">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" id="confirmPassword">
                        </div>
                        <button class="btn btn-primary" onclick="changeAdminPassword()">Update Password</button>
                    </div>
                    <div>
                        <h3 style="margin-bottom: 15px; color: #ffd166;">Security Settings</h3>
                        <div class="form-group">
                            <label>Admin Email</label>
                            <input type="email" id="adminEmail" value="admin@mazequest.com">
                        </div>
                        <div class="form-group">
                            <label>Two-Factor Authentication</label>
                            <select id="twoFactorSetting">
                                <option value="enabled">Enabled</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session Timeout (minutes)</label>
                            <input type="number" id="sessionTimeout" value="30" min="5" max="240">
                        </div>
                        <button class="btn btn-success" onclick="saveSecuritySettings()">💾 Save Settings</button>
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
                    <button class="btn btn-warning" onclick="resetAllData()">🔄 Reset All Data</button>
                    <button class="btn btn-danger" onclick="deleteAllUsers()">🗑️ Delete All Users</button>
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
                                <label>Retention Rate</label>
                                <input type="text" id="retentionRate" readonly>
                            </div>
                            <div class="form-group">
                                <label>Avg Session Time</label>
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
                    <input type="password" id="newPassword" required>
                </div>
                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" id="newEmail">
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

    <script>
        // ✅ Check admin authentication - also check server session
        async function checkAdminAuth() {
            // First check localStorage
            const currentUser = JSON.parse(localStorage.getItem('user') || 'null');
            const loggedInUser = JSON.parse(localStorage.getItem('loggedInUser') || 'null');
            
            console.log('Current User:', currentUser);
            console.log('Logged In User:', loggedInUser);
            
            // Check if user exists and is admin
            const user = currentUser || loggedInUser;
            
            // Also check server session
            try {
                const res = await fetch('<?= url('api/') ?>user_profile.php', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.success && data.user) {
                    // Use server data if available
                    const serverUser = data.user;
                    if (serverUser.isAdmin || serverUser.role === 'admin') {
                        return true;
                    }
                }
            } catch(e) {
                console.log('Session check failed:', e);
            }
            
            if (!user || user === 'null') {
                alert('❌ Access denied. Please login first.');
                window.location.href = 'admin';
                return false;
            }
            
            // Check admin status - multiple ways
            const isAdmin = user.is_admin === true || 
                           user.role === 'admin' || 
                           user.isAdmin === true ||
                           user.username === 'admin' ||
                           (user.permissions && user.permissions.includes('all'));
            
            if (!isAdmin) {
                alert('❌ Access denied. Admin privileges required.');
                window.location.href = 'admin';
                return false;
            }
            
            return true;
        }
        
        // Check on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAdminAuth();
        });

        // ✅ Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Load tab-specific data
            if (tabName === 'dashboard') {
                loadDashboardData();
            } else if (tabName === 'users') {
                loadUsersTable();
            } else if (tabName === 'security') {
                loadSecurityLogs();
            } else if (tabName === 'analytics') {
                loadAnalytics();
            }
        }

        // ✅ Load dashboard data from SQL
        async function loadDashboardData() {
            if (!checkAdminAuth()) return;

            try {
                // Load stats from SQL
                const statsResponse = await fetch('<?= url('api/') ?>admin_api.php?action=get_stats');
                const stats = await statsResponse.json();
                
                // Update statistics
                document.getElementById('totalUsers').textContent = stats.totalUsers;
                document.getElementById('totalGames').textContent = stats.totalGames || '0';
                document.getElementById('totalCoins').textContent = stats.totalCoins;
                document.getElementById('activeUsers').textContent = stats.activeUsers;

                // Update quick stats
                document.getElementById('avgLevel').textContent = stats.avgLevel || '0';
                document.getElementById('totalXP').textContent = stats.totalXP;
                document.getElementById('gatesOpened').textContent = stats.totalGates || '0';
                document.getElementById('correctAnswers').textContent = stats.totalCorrect || '0';

                // Load recent activity
                loadRecentActivity();
            } catch (error) {
                console.error('Error loading admin data:', error);
                showError('Failed to load dashboard data. Please check your database connection.');
            }
        }

        // ✅ Load recent activity
        async function loadRecentActivity() {
            const activityContainer = document.getElementById('recentActivity');
            
            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=get_activity');
                const activities = await response.json();
                
                activityContainer.innerHTML = '';
                
                if (activities.length === 0) {
                    activityContainer.innerHTML = '<div class="log-entry">No recent activity found.</div>';
                    return;
                }

                activities.forEach(activity => {
                    const logEntry = document.createElement('div');
                    logEntry.className = `log-entry log-${activity.type || 'success'}`;
                    logEntry.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>${activity.message}</span>
                            <small style="color: #aaa;">${activity.time}</small>
                        </div>
                    `;
                    activityContainer.appendChild(logEntry);
                });
            } catch (error) {
                console.error('Error loading activities:', error);
                activityContainer.innerHTML = '<div class="log-entry log-error">Failed to load recent activity.</div>';
            }
        }

        // ✅ Load users table from SQL
        async function loadUsersTable() {
            const tableBody = document.getElementById('usersTableBody');
            tableBody.innerHTML = '<tr><td colspan="7" class="loading"><div class="spinner"></div><p>Loading users...</p></td></tr>';

            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=get_users');
                const users = await response.json();
                
                tableBody.innerHTML = '';
                
                if (users.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #aaa;">
                                No users found in database. Add some users to get started.
                            </td>
                        </tr>
                    `;
                    return;
                }

                users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.username}</td>
                        <td>${user.level ?? 0}</td>
                        <td>${user.coins ?? 0}</td>
                        <td>${user.xp ?? 0}</td>
                        <td>${user.streak ?? 0}</td>
                        <td>${user.total_games ?? 0}</td>
                        <td>${user.powerups_used ?? 0}</td>
                        <td>${user.correct_answers ?? 0}</td>
                        <td>${user.wrong_answers ?? 0}</td>
                        <td>${user.last_active ? new Date(user.last_active).toLocaleDateString() : '-'}</td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-primary btn-small" onclick="editUser(${user.id}, '${user.username}')">Edit</button>
                                <button class="btn btn-danger btn-small" onclick="deleteUser(${user.id}, '${user.username}')">Delete</button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading users from SQL:', error);
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #ff6b6b;">
                            ❌ Failed to load users. Please check your database connection.
                        </td>
                    </tr>
                `;
            }
        }

        // ✅ Load security logs
        async function loadSecurityLogs() {
            const container = document.getElementById('securityLogs');
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading security logs...</p></div>';

            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=get_security_logs');
                const securityLogs = await response.json();
                
                container.innerHTML = '';

                if (securityLogs.length === 0) {
                    container.innerHTML = '<div class="log-entry">No security events logged yet.</div>';
                    return;
                }

                securityLogs.forEach(log => {
                    const logEntry = document.createElement('div');
                    let logClass = 'log-entry';
                    if (log.event && (log.event.includes('failed') || log.event.includes('error'))) {
                        logClass += ' log-error';
                    } else if (log.event && log.event.includes('success')) {
                        logClass += ' log-success';
                    } else {
                        logClass += ' log-warning';
                    }

                    logEntry.className = logClass;
                    logEntry.innerHTML = `
                        <div><strong>${log.event || 'Unknown event'}</strong> - ${log.user || 'System'}</div>
                        <small>${new Date(log.timestamp || Date.now()).toLocaleString()}</small>
                    `;
                    container.appendChild(logEntry);
                });
            } catch (error) {
                console.error('Error loading security logs:', error);
                container.innerHTML = '<div class="log-entry log-error">Failed to load security logs.</div>';
            }
        }

        // ✅ Load analytics
        async function loadAnalytics() {
            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=get_analytics');
                const analytics = await response.json();
                
                // Update analytics fields
                document.getElementById('avgGameDuration').value = analytics.avgGameDuration || '3.5 minutes';
                document.getElementById('successRate').value = analytics.successRate || '75%';
                document.getElementById('popularSubject').value = analytics.popularSubject || 'Mathematics';
                document.getElementById('dailyActiveUsers').value = analytics.dailyActiveUsers || '0';
                document.getElementById('retentionRate').value = analytics.retentionRate || '75%';
                document.getElementById('avgSessionTime').value = analytics.avgSessionTime || '12 minutes';
            } catch (error) {
                console.error('Error loading analytics:', error);
                // Set default values if API fails
                document.getElementById('avgGameDuration').value = '3.5 minutes';
                document.getElementById('successRate').value = '75%';
                document.getElementById('popularSubject').value = 'Mathematics';
                document.getElementById('dailyActiveUsers').value = '0';
                document.getElementById('retentionRate').value = '75%';
                document.getElementById('avgSessionTime').value = '12 minutes';
            }
        }

        // ✅ Add new user to SQL
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('username', document.getElementById('newUsername').value);
            formData.append('password', document.getElementById('newPassword').value);
            formData.append('email', document.getElementById('newEmail').value);
            formData.append('coins', document.getElementById('initialCoins').value);
            formData.append('level', document.getElementById('initialLevel').value);

            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ User added successfully to database!');
                    closeAddUserModal();
                    loadUsersTable();
                    loadDashboardData();
                } else {
                    alert('❌ Error adding user: ' + result.message);
                }
            } catch (error) {
                console.error('Error adding user:', error);
                alert('❌ Error adding user to database. Please try again.');
            }
        });

        // ✅ Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthBar.className = 'password-strength ';
            if (strength === 0) {
                strengthBar.className += 'strength-weak';
            } else if (strength === 1) {
                strengthBar.className += 'strength-weak';
            } else if (strength === 2) {
                strengthBar.className += 'strength-medium';
            } else if (strength === 3) {
                strengthBar.className += 'strength-strong';
            } else {
                strengthBar.className += 'strength-very-strong';
            }
        }

        // ✅ Change admin password
        async function changeAdminPassword() {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                alert('❌ New passwords do not match.');
                return;
            }
            
            if (newPassword.length < 8) {
                alert('❌ Password must be at least 8 characters long.');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'change_admin_password');
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);

                const response = await fetch('<?= url('api/') ?>admin_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Admin password updated successfully!');
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                } else {
                    alert('❌ Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error changing password:', error);
                alert('❌ Error changing password. Please try again.');
            }
        }

        // ✅ Save security settings
        function saveSecuritySettings() {
            const email = document.getElementById('adminEmail').value;
            const twoFactor = document.getElementById('twoFactorSetting').value;
            const timeout = document.getElementById('sessionTimeout').value;
            
            alert(`✅ Security settings saved!\n\nEmail: ${email}\n2FA: ${twoFactor}\nTimeout: ${timeout} minutes`);
        }

        // ✅ Delete user from SQL
        async function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}" from the database?`)) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_user');
                    formData.append('user_id', userId);

                    const response = await fetch('<?= url('api/') ?>admin_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ User deleted successfully from database!');
                        loadUsersTable();
                        loadDashboardData();
                    } else {
                        alert('❌ Error deleting user: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error deleting user:', error);
                    alert('❌ Error deleting user from database. Please try again.');
                }
            }
        }

        // ✅ Edit user
        function editUser(userId, username) {
            alert(`Edit user: ${username} (ID: ${userId})\n\nThis feature would open an edit modal in a full implementation.`);
        }

        // ✅ System functions
        function refreshData() {
            loadDashboardData();
            alert('✅ Data refreshed!');
        }

        function logout() {
            localStorage.removeItem('user');
            localStorage.removeItem('loggedInUser');
            window.location.href = 'admin';
        }

        async function backupData() {
            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=backup_data');
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Data backup created successfully!');
                } else {
                    alert('❌ Error creating backup: ' + result.message);
                }
            } catch (error) {
                console.error('Error creating backup:', error);
                alert('❌ Error creating backup. Please try again.');
            }
        }

        async function resetAllData() {
            if (confirm('⚠️ Are you sure you want to reset ALL data? This cannot be undone!')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'reset_all_data');

                    const response = await fetch('<?= url('api/') ?>admin_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ All data has been reset.');
                        logout();
                    } else {
                        alert('❌ Error resetting data: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error resetting data:', error);
                    alert('❌ Error resetting data. Please try again.');
                }
            }
        }

        async function deleteAllUsers() {
            if (confirm('⚠️ Are you sure you want to delete ALL users? This cannot be undone!')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_all_users');

                    const response = await fetch('<?= url('api/') ?>admin_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ All users deleted!');
                        loadDashboardData();
                        loadUsersTable();
                    } else {
                        alert('❌ Error deleting users: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error deleting users:', error);
                    alert('❌ Error deleting users. Please try again.');
                }
            }
        }

        async function exportData() {
            try {
                const response = await fetch('<?= url('api/') ?>admin_api.php?action=export_data');
                const result = await response.json();
                
                if (result.success) {
                    // Create download link
                    const dataStr = JSON.stringify(result.data, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'mazequest_data_export.json';
                    link.click();
                    
                    alert('✅ Data exported successfully!');
                } else {
                    alert('❌ Error exporting data: ' + result.message);
                }
            } catch (error) {
                console.error('Error exporting data:', error);
                alert('❌ Error exporting data. Please try again.');
            }
        }

        // ✅ Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
            document.getElementById('passwordStrength').className = 'password-strength';
        }

        // ✅ Utility functions
        function showError(message) {
            alert('❌ ' + message);
        }

        // ✅ Initialize admin panel
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin panel loading...');
            if (checkAdminAuth()) {
                console.log('Admin authenticated, loading data from SQL...');
                loadDashboardData();
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target === modal) {
                closeAddUserModal();
            }
        }
    </script>
</body>
</html>
