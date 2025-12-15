<?php
// login.php - User and Admin authentication
header('Content-Type: application/json');
session_start();

// Suppress HTML errors for JSON responses (enable for debugging)
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Read inputs
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$loginType = isset($_POST['loginType']) ? trim($_POST['loginType']) : 'user'; // user or admin

// Validation
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

try {
    // Query user from users table - use SELECT * to get all available columns
    // Then handle missing columns gracefully
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Ensure we have the required fields
    if (!isset($user['id']) || !isset($user['username']) || !isset($user['email']) || !isset($user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid user data in database.']);
        exit;
    }

    // Set defaults for missing columns
    if (!isset($user['role'])) $user['role'] = 'user';
    if (!isset($user['is_active'])) $user['is_active'] = 1;
    if (!isset($user['is_admin'])) $user['is_admin'] = 0;
    if (!isset($user['coins'])) $user['coins'] = 100;
    if (!isset($user['level'])) $user['level'] = 1;
    if (!isset($user['xp'])) $user['xp'] = 0;
    if (!isset($user['streak'])) $user['streak'] = 0;
    if (!isset($user['avatar'])) $user['avatar'] = null;

    // Check if user is active
    if (isset($user['is_active']) && ($user['is_active'] == 0 || $user['is_active'] === false)) {
        echo json_encode(['success' => false, 'message' => 'Account is deactivated. Please contact support.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Determine if user is admin
    $isAdmin = ($user['role'] === 'admin' || (isset($user['is_admin']) && ($user['is_admin'] == 1 || $user['is_admin'] === true)));

    // Check if admin login is required but user is not admin
    if ($loginType === 'admin' && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Admin access denied. This account is not an admin.']);
        exit;
    }

    // Create session
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['is_admin'] = $isAdmin;

    // Update last login time (ignore errors if columns don't exist)
    try {
        $updateStmt = $db->prepare('UPDATE users SET last_login = NOW(), login_count = COALESCE(login_count, 0) + 1 WHERE id = :id');
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
    } catch (Exception $e) {
        // Try without login_count if it doesn't exist
        try {
            $updateStmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
        } catch (Exception $e2) {
            // Ignore update errors, login should still succeed
        }
    }

    // Prepare response data
    $response = [
        'success' => true,
        'message' => 'Login successful.',
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'user',
        'coins' => (int)($user['coins'] ?? 100),
        'level' => (int)($user['level'] ?? 1),
        'xp' => (int)($user['xp'] ?? 0),
        'streak' => (int)($user['streak'] ?? 0),
        'avatar' => $user['avatar'] ?? 'https://i.pravatar.cc/50'
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // More specific error for database issues
    http_response_code(500);
    $errorMsg = $e->getMessage();
    error_log('Login PDO Error: ' . $errorMsg); // Log for debugging
    
    // For debugging - show actual error (remove in production)
    $debugMode = true; // Set to false in production
    $errorDetail = $debugMode ? $errorMsg : 'Database connection error';
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please check your connection.',
        'error' => $errorDetail,
        'file' => $debugMode ? $e->getFile() : null,
        'line' => $debugMode ? $e->getLine() : null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    error_log('Login Error: ' . $errorMsg); // Log for debugging
    
    // For debugging - show actual error (remove in production)
    $debugMode = true; // Set to false in production
    $errorDetail = $debugMode ? $errorMsg : 'An error occurred';
    
    echo json_encode([
        'success' => false,
        'message' => 'Login failed. Please try again later.',
        'error' => $errorDetail,
        'file' => $debugMode ? $e->getFile() : null,
        'line' => $debugMode ? $e->getLine() : null
    ]);
}
?>

