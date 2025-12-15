<?php
require_once 'config.php';

header('Content-Type: application/json');
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$type = $_GET['type'] ?? 'xp'; // xp, coins, level, streak
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    exit;
}

try {
    // Build query based on type
    $orderColumn = 'xp';
    switch ($type) {
        case 'coins':
            $orderColumn = 'coins';
            break;
        case 'level':
            $orderColumn = 'level';
            break;
        case 'streak':
            $orderColumn = 'streak';
            break;
        default:
            $orderColumn = 'xp';
    }

    // First check if table exists and what columns it has
    $checkStmt = $db->query("DESCRIBE users");
    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build column list based on what exists
    $selectColumns = ['username'];
    foreach (['level', 'xp', 'coins', 'streak', 'avatar', 'total_games'] as $col) {
        if (in_array($col, $columns)) {
            $selectColumns[] = $col;
        }
    }
    
    $query = "SELECT " . implode(', ', $selectColumns) . " 
              FROM users 
              ORDER BY {$orderColumn} DESC, " . (in_array('xp', $columns) ? 'xp' : 'id') . " DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $leaderboard = [];
    $rank = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $leaderboard[] = [
            'rank' => $rank++,
            'username' => $row['username'] ?? 'Unknown',
            'level' => isset($row['level']) ? (int)$row['level'] : 1,
            'xp' => isset($row['xp']) ? (int)$row['xp'] : 0,
            'coins' => isset($row['coins']) ? (int)$row['coins'] : 0,
            'streak' => isset($row['streak']) ? (int)$row['streak'] : 0,
            'avatar' => ($row['avatar'] ?? '') ?: 'https://i.pravatar.cc/50',
            'games_played' => isset($row['total_games']) ? (int)$row['total_games'] : 0
        ];
    }

    echo json_encode([
        'success' => true, 
        'leaderboard' => $leaderboard,
        'total_players' => count($leaderboard),
        'type' => $type,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading leaderboard: ' . $e->getMessage()
    ]);
}
?>