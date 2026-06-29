<?php
header('Content-Type: application/json');

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Services/AuthService.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$authService = new AuthService($db);
$result = $authService->authenticateUser($email, $password);

if ($result['success']) {
    $user = $result['user'];
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => 'user',
        'coins' => (int)($user['coins'] ?? 100),
        'level' => (int)($user['level'] ?? 1),
        'xp' => (int)($user['xp'] ?? 0),
        'streak' => (int)($user['streak'] ?? 0),
        'avatar' => $user['avatar'] ?? 'https://i.pravatar.cc/50'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
