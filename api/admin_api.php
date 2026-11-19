<?php
// Enable full error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration (PDO)
require_once __DIR__ . '/../Config/database.php';
$database = new Database();
$db = $database->getConnection();

// Safe action retrieval
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Admin guard (use per-action to avoid breaking existing simple GETs if needed)
function require_admin() {
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
        exit;
    }
}

// Helper function for safe queries
function safeQuery($db, $sql, $default = 0) {
    $stmt = $db->query($sql);
    if ($stmt) {
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }
    return $default;
}

// Helper: check if table exists in current DB
function tableExists(PDO $db, $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :t");
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Exception $e) {
        return false;
    }
}

try {
    switch($action) {
        case 'get_stats':
            // Prefer new tables if available
            $hasPlayer = tableExists($db, 'player');
            $hasUsers  = tableExists($db, 'users');
            $hasGS     = tableExists($db, 'game_session');

            $totalUsers = 0;
            if ($hasPlayer) {
                $totalUsers = intval(safeQuery($db, "SELECT COUNT(*) FROM player"));
            } elseif ($hasUsers) {
                $totalUsers = intval(safeQuery($db, "SELECT COUNT(*) FROM users WHERE role='user'"));
            }

            // Coins / Level / XP: try player columns, else users
            $totalCoins = 0; $avgLevel = 0; $totalXP = 0; $activeUsers = 0; $totalGames = 0;
            if ($hasPlayer && tableExists($db, 'leaderboard')) {
                // If coins exists in player use it; else 0
                try { $totalCoins = intval(safeQuery($db, "SELECT COALESCE(SUM(coins),0) FROM player")); } catch (Exception $e) { $totalCoins = 0; }
                try { $avgLevel = round(floatval(safeQuery($db, "SELECT AVG(level) FROM player")), 1); } catch (Exception $e) { $avgLevel = 0; }
                try { $totalXP = intval(safeQuery($db, "SELECT COALESCE(SUM(xp),0) FROM player")); } catch (Exception $e) { $totalXP = 0; }
            }
            if (($totalCoins === 0 && $avgLevel === 0 && $totalXP === 0) && $hasUsers) {
                $totalCoins = intval(safeQuery($db, "SELECT COALESCE(SUM(coins),0) FROM users"));
                $avgLevel   = round(floatval(safeQuery($db, "SELECT AVG(level) FROM users")), 1);
                $totalXP    = intval(safeQuery($db, "SELECT COALESCE(SUM(xp),0) FROM users"));
            }

            // Active users: recent sessions or users.last_login
            if ($hasGS) {
                $activeUsers = intval(safeQuery($db, "SELECT COUNT(DISTINCT player_id) FROM game_session WHERE start_time >= NOW() - INTERVAL 1 DAY"));
                $totalGames  = intval(safeQuery($db, "SELECT COUNT(*) FROM game_session"));
            } elseif ($hasUsers) {
                $activeUsers = intval(safeQuery($db, "SELECT COUNT(*) FROM users WHERE last_login >= NOW() - INTERVAL 1 DAY"));
            }

            $stats = [
                'totalUsers'   => $totalUsers,
                'totalCoins'   => $totalCoins,
                'activeUsers'  => $activeUsers,
                'avgLevel'     => $avgLevel,
                'totalXP'      => $totalXP,
                'totalGames'   => $totalGames,
                'totalGates'   => 0,
                'totalCorrect' => 0
            ];
            echo json_encode($stats);
            break;

        case 'get_users':
            if (tableExists($db, 'player')) {
                // Try to read coins/level/xp if present; fallback to 0
                $cols = 'player_id as id, username, fullname, ' .
                        'COALESCE(level, 0) AS level, COALESCE(coins, 0) AS coins, COALESCE(xp, 0) AS xp, ' .
                        '0 AS streak, NOW() AS last_active';
                $stmt = $db->query("SELECT $cols FROM player ORDER BY player_id DESC");
                $users = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                echo json_encode($users);
            } else {
                $sql = "SELECT id, username, level, coins, xp, streak,
                               COALESCE(last_login, NOW()) AS last_active
                        FROM users
                        WHERE role = 'user'
                        ORDER BY id DESC";
                $stmt = $db->query($sql);
                $users = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                echo json_encode($users);
            }
            break;

        case 'add_user':
            $username = $_POST['username'] ?? '';
            $passwordPlain = $_POST['password'] ?? '';
            $email    = $_POST['email'] ?? '';
            $coins    = intval($_POST['coins'] ?? 0);
            $level    = intval($_POST['level'] ?? 0);

            // Check if username exists
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $checkStmt->bindParam(':username', $username);
            $checkStmt->execute();
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                break;
            }

            $sql = "INSERT INTO users (username, password, email, coins, level) 
                    VALUES (:username, :password, :email, :coins, :level)";
            $stmt = $db->prepare($sql);
            $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $passwordHash);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':coins', $coins, PDO::PARAM_INT);
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding user']);
            }
            break;

        case 'delete_user':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $sql = "DELETE FROM users WHERE id = :id AND role != 'admin'";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting user']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            }
            break;

        case 'get_activity':
            $activities = [
                ['type' => 'success', 'message' => 'Database connected successfully', 'time' => date('H:i:s')],
                ['type' => 'info', 'message' => 'Admin panel loaded', 'time' => date('H:i:s')]
            ];
            echo json_encode($activities);
            break;

        case 'get_security_logs':
            $logs = [
                ['event' => 'Admin login successful', 'user' => 'admin', 'timestamp' => date('Y-m-d H:i:s')]
            ];
            echo json_encode($logs);
            break;

        case 'export_data':
            // Export existing core tables plus new diagram tables if present
            $export = [];
            $sets = [
                'users' => 'SELECT id, username, email, role, coins, level, xp, streak, avatar, last_checkin, is_active, last_login, login_count FROM users',
            ];
            foreach ($sets as $name => $sql) {
                if (tableExists($db, $name)) {
                    $export[$name] = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            // Diagram tables (optional)
            if (tableExists($db, 'admin')) {
                $export['admin'] = $db->query('SELECT admin_id, fullname, email, username FROM admin')->fetchAll(PDO::FETCH_ASSOC);
            }
            if (tableExists($db, 'player')) {
                $export['player'] = $db->query('SELECT player_id, fullname, username, email, contact, total_score FROM player')->fetchAll(PDO::FETCH_ASSOC);
            }
            if (tableExists($db, 'maze')) {
                $export['maze'] = $db->query('SELECT maze_id, maze_name, maze_level, difficulty_level, description, created_by_admin_id FROM maze')->fetchAll(PDO::FETCH_ASSOC);
            }
            if (tableExists($db, 'game_session')) {
                $export['game_session'] = $db->query('SELECT session_id, player_id, maze_id, start_time, end_time, score_earned FROM game_session')->fetchAll(PDO::FETCH_ASSOC);
            }
            if (tableExists($db, 'leaderboard')) {
                $export['leaderboard'] = $db->query('SELECT leaderboard_id, player_id, total_score, rank FROM leaderboard')->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'data' => $export]);
            break;

        case 'get_analytics':
            $daily_active = intval(safeQuery($db, "SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()"));
            $analytics = [
                'avgGameDuration' => '3.5 minutes',
                'successRate'     => '75%',
                'popularSubject'  => 'Mathematics',
                'dailyActiveUsers'=> $daily_active,
                'retentionRate'   => '75%',
                'avgSessionTime'  => '12 minutes'
            ];
            echo json_encode($analytics);
            break;

        case 'change_admin_password':
            require_admin();
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            if (strlen($new) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password too short']);
                break;
            }
            // Load current admin user by session id
            $uid = intval($_SESSION['user_id'] ?? 0);
            if ($uid <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid session']); break; }
            $stmt = $db->prepare("SELECT id, password FROM users WHERE id = :id AND role = 'admin' LIMIT 1");
            $stmt->bindParam(':id', $uid, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success' => false, 'message' => 'Admin not found']); break; }
            if (!password_verify($current, $row['password'])) {
                echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
                break;
            }
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $upd = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
            $upd->bindParam(':p', $hash);
            $upd->bindParam(':id', $uid, PDO::PARAM_INT);
            $ok = $upd->execute();
            echo json_encode(['success' => $ok ? true : false, 'message' => $ok ? 'Password updated' : 'Failed to update password']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}
?>
