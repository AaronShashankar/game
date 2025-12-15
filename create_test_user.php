<?php
header('Content-Type: application/json');
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Check config.php and MySQL service.']);
    exit;
}

// Read query params or defaults
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'user';
$username = isset($_GET['username']) ? trim($_GET['username']) : ($type === 'admin' ? 'admin' : 'player1');
$email = isset($_GET['email']) ? trim($_GET['email']) : ($type === 'admin' ? 'admin@mazequest.com' : 'player1@example.com');
$password = isset($_GET['password']) ? trim($_GET['password']) : ($type === 'admin' ? 'admin123' : 'secret123');
$role = isset($_GET['role']) ? trim($_GET['role']) : ($type === 'admin' ? 'admin' : 'user');

try {
    // Ensure password column can hold bcrypt hashes
    try {
        $db->exec("ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL");
    } catch (Exception $e) { /* ignore if already sized */ }

    // Ensure optional columns exist
    $alterStatements = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS coins INT NOT NULL DEFAULT 100",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS level INT NOT NULL DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS xp INT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS streak INT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_checkin DATE NULL"
    ];
    foreach ($alterStatements as $sql) {
        try { $db->exec($sql); } catch (Exception $e) { /* ignore */ }
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $hash = password_hash($password, PASSWORD_BCRYPT);

    if ($existing) {
        // Update existing user password and role, ensure active
        $upd = $db->prepare("UPDATE users SET username = :u, password = :p, role = :r, is_active = 1 WHERE id = :id");
        $upd->bindParam(':u', $username);
        $upd->bindParam(':p', $hash);
        $upd->bindParam(':r', $role);
        $upd->bindParam(':id', $existing['id']);
        $upd->execute();
        $userId = (int)$existing['id'];
        $action = 'updated';
    } else {
        // Insert new user with defaults
        $ins = $db->prepare("INSERT INTO users (username,email,password,role,coins,level,xp,streak,is_active) VALUES (:u,:e,:p,:r,100,1,50,0,1)");
        $ins->bindParam(':u', $username);
        $ins->bindParam(':e', $email);
        $ins->bindParam(':p', $hash);
        $ins->bindParam(':r', $role);
        $ins->execute();
        $userId = (int)$db->lastInsertId();
        $action = 'created';
    }

    echo json_encode([
        'success' => true,
        'message' => "Test user {$action}.",
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'role' => $role
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error creating test user', 'error' => $e->getMessage()]);
}
