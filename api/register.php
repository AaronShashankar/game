<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
@ini_set('html_errors', 0);

require_once __DIR__ . '/../Config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Read inputs
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm = isset($_POST['confirm']) ? trim($_POST['confirm']) : '';

if ($username === '' || $email === '' || $password === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

try {
    // Ensure schema supports bcrypt and unique email/username
    try { $db->exec("ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uniq_email (email)"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uniq_username (username)"); } catch (Exception $e) {}

    // Check existing username/email
    $chk = $db->prepare('SELECT id FROM users WHERE email = :e OR username = :u LIMIT 1');
    $chk->bindParam(':e', $email);
    $chk->bindParam(':u', $username);
    $chk->execute();
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or Email already exists.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $ins = $db->prepare('INSERT INTO users (username,email,password,role,coins,level,xp,streak,is_active) VALUES (:u,:e,:p,\'user\',100,1,0,0,1)');
    $ins->bindParam(':u', $username);
    $ins->bindParam(':e', $email);
    $ins->bindParam(':p', $hash);
    $ins->execute();

    echo json_encode(['success' => true, 'message' => 'Registration successful. Please log in.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed.', 'error' => $e->getMessage()]);
}
