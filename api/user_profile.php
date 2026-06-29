<?php
header('Content-Type: application/json');
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
if ($db === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, username, email, role, coins, level, xp, streak, avatar, last_checkin, last_login, login_count FROM users WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'user' => [
            'username' => $u['username'],
            'email' => $u['email'],
            'coins' => (int)$u['coins'],
            'level' => (int)$u['level'],
            'xp' => (int)$u['xp'],
            'streak' => (int)$u['streak'],
            'avatar' => $u['avatar'] ?: 'https://i.pravatar.cc/50',
            'isAdmin' => $u['role'] === 'admin',
            'lastCheckin' => $u['last_checkin'],
            'last_login' => $u['last_login'],
            'login_count' => isset($u['login_count']) ? (int)$u['login_count'] : null
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching profile', 'error' => $e->getMessage()]);
}
