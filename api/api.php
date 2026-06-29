<?php
// api.php
header('Content-Type: application/json');
require_once __DIR__ . '/../Config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch($action) {
    case 'getUser':
        getUser($db);
        break;
    case 'updateUser':
        updateUser($db);
        break;
    case 'getAllUsers':
        getAllUsers($db);
        break;
    case 'addUser':
        addUser($db);
        break;
    case 'deleteUser':
        deleteUser($db);
        break;
    case 'getGames':
        getGames($db);
        break;
    case 'getRewards':
        getRewards($db);
        break;
    case 'claimBonus':
        claimBonus($db);
        break;
    case 'spinWheel':
        spinWheel($db);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getUser($db) {
    $user_id = $_GET['user_id'] ?? 1; // In real app, get from session
    
    $query = "SELECT u.*, GROUP_CONCAT(ub.badge_name) as badges 
              FROM users u 
              LEFT JOIN user_badges ub ON u.id = ub.user_id 
              WHERE u.id = :id 
              GROUP BY u.id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        $user['badges'] = $user['badges'] ? explode(',', $user['badges']) : [];
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
}

function updateUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $query = "UPDATE users SET 
              username = :username, 
              email = :email, 
              avatar = :avatar 
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $data['username']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':avatar', $data['avatar']);
    $stmt->bindParam(':id', $data['id']);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

function getAllUsers($db) {
    $query = "SELECT id, username, email, role, coins, level, xp, streak, avatar FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'users' => $users]);
}

function addUser($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $query = "INSERT INTO users (username, email, password, role, coins, level, xp, avatar) 
              VALUES (:username, :email, :password, :role, :coins, :level, :xp, :avatar)";
    
    $stmt = $db->prepare($query);
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt->bindParam(':username', $data['username']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':password', $password_hash);
    $stmt->bindParam(':role', $data['role']);
    $stmt->bindParam(':coins', $data['coins']);
    $stmt->bindParam(':level', $data['level']);
    $stmt->bindParam(':xp', $data['xp']);
    $stmt->bindParam(':avatar', $data['avatar']);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true, 'user_id' => $db->lastInsertId()]);
    } else {
        echo json_encode(['success' => false]);
    }
}

function deleteUser($db) {
    $user_id = $_GET['user_id'] ?? '';
    
    $query = "DELETE FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

function getGames($db) {
    $query = "SELECT * FROM games WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'games' => $games]);
}

function getRewards($db) {
    $query = "SELECT * FROM rewards";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rewards' => $rewards]);
}

function claimBonus($db) {
    $user_id = $_GET['user_id'] ?? 1;
    $today = date('Y-m-d');
    
    // Check if already claimed today
    $query = "SELECT last_checkin FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user['last_checkin'] == $today) {
        echo json_encode(['success' => false, 'error' => 'Already claimed today']);
        return;
    }
    
    // Update user with bonus
    $query = "UPDATE users SET 
              coins = coins + 50, 
              xp = xp + 20, 
              streak = streak + 1, 
              last_checkin = :today 
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':today', $today);
    $stmt->bindParam(':id', $user_id);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true, 'coins' => 50, 'xp' => 20]);
    } else {
        echo json_encode(['success' => false]);
    }
}

function spinWheel($db) {
    $user_id = $_GET['user_id'] ?? 1;
    
    // Check if user has enough coins
    $query = "SELECT coins FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user['coins'] < 5) {
        echo json_encode(['success' => false, 'error' => 'Not enough coins']);
        return;
    }
    
    // Deduct coins
    $query = "UPDATE users SET coins = coins - 5 WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    
    // Get random reward
    $query = "SELECT * FROM rewards ORDER BY RAND() LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Apply reward
    if($reward['type'] == 'coins') {
        $query = "UPDATE users SET coins = coins + :value WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':value', $reward['value']);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'reward' => $reward]);
}
?>
