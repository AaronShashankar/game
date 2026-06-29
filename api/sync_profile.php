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

$userId = (int)$_SESSION['user_id'];

// Accept FormData or JSON
$input = [
    'username' => isset($_POST['username']) ? trim($_POST['username']) : null,
    'email' => isset($_POST['email']) ? trim($_POST['email']) : null,
    'coins' => isset($_POST['coins']) ? $_POST['coins'] : null,
    'level' => isset($_POST['level']) ? $_POST['level'] : null,
    'xp' => isset($_POST['xp']) ? $_POST['xp'] : null,
    'streak' => isset($_POST['streak']) ? $_POST['streak'] : null,
    'avatar' => isset($_POST['avatar']) ? $_POST['avatar'] : null,
    'last_checkin' => isset($_POST['last_checkin']) ? $_POST['last_checkin'] : null,
    'total_games' => isset($_POST['total_games']) ? $_POST['total_games'] : null,
    'correct_answers' => isset($_POST['correct_answers']) ? $_POST['correct_answers'] : null,
    'wrong_answers' => isset($_POST['wrong_answers']) ? $_POST['wrong_answers'] : null,
    'powerups_used' => isset($_POST['powerups_used']) ? $_POST['powerups_used'] : null,
];

if ($input['username'] === null && $input['email'] === null) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            foreach ($input as $k => $v) {
                if (isset($json[$k])) { $input[$k] = $json[$k]; }
            }
        }
    }
}

try {
    $fields = [];
    $params = [':id' => $userId];

    if ($input['username'] !== null && $input['username'] !== '') { $fields[] = 'username = :u'; $params[':u'] = $input['username']; }
    if ($input['email'] !== null && $input['email'] !== '') { $fields[] = 'email = :e'; $params[':e'] = $input['email']; }

    if ($input['coins'] !== null && $input['coins'] !== '') { $fields[] = 'coins = :c'; $params[':c'] = (int)$input['coins']; }
    if ($input['level'] !== null && $input['level'] !== '') { $fields[] = 'level = :l'; $params[':l'] = (int)$input['level']; }
    if ($input['xp'] !== null && $input['xp'] !== '') { $fields[] = 'xp = :x'; $params[':x'] = (int)$input['xp']; }
    if ($input['streak'] !== null && $input['streak'] !== '') { $fields[] = 'streak = :s'; $params[':s'] = (int)$input['streak']; }
    if ($input['avatar'] !== null) { $fields[] = 'avatar = :a'; $params[':a'] = $input['avatar']; }
    if ($input['last_checkin'] !== null && $input['last_checkin'] !== '') { $fields[] = 'last_checkin = :lc'; $params[':lc'] = $input['last_checkin']; }
    if ($input['total_games'] !== null && $input['total_games'] !== '') { $fields[] = 'total_games = :tg'; $params[':tg'] = (int)$input['total_games']; }
    if ($input['correct_answers'] !== null && $input['correct_answers'] !== '') { $fields[] = 'correct_answers = :ca'; $params[':ca'] = (int)$input['correct_answers']; }
    if ($input['wrong_answers'] !== null && $input['wrong_answers'] !== '') { $fields[] = 'wrong_answers = :wa'; $params[':wa'] = (int)$input['wrong_answers']; }
    if ($input['powerups_used'] !== null && $input['powerups_used'] !== '') { $fields[] = 'powerups_used = :pu'; $params[':pu'] = (int)$input['powerups_used']; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();

    // Mirror changes to player table if it exists, matched by username/email
    try {
        // Fetch current user's identifiers
        $who = $db->prepare("SELECT username, email, password FROM users WHERE id = :id LIMIT 1");
        $who->bindParam(':id', $userId, PDO::PARAM_INT);
        $who->execute();
        $ident = $who->fetch(PDO::FETCH_ASSOC);
        if ($ident) {
            // Ensure player row exists; if not, create it
            try {
                $selp = $db->prepare('SELECT player_id FROM player WHERE username = :u OR email = :e LIMIT 1');
                $selp->execute([':u'=>$ident['username'], ':e'=>$ident['email']]);
                $prow = $selp->fetch(PDO::FETCH_ASSOC);
                if (!$prow) {
                    $insP = $db->prepare('INSERT INTO player (fullname, username, email, password, total_score, coins, level, xp, streak) VALUES (:f,:u,:e,:p,0,0,1,0,0)');
                    $insP->execute([
                        ':f' => $ident['username'],
                        ':u' => $ident['username'],
                        ':e' => $ident['email'],
                        ':p' => $ident['password']
                    ]);
                }
            } catch (Exception $ie) { /* ignore if player table missing */ }
            // Build dynamic update for player using provided fields
            $pfields = [];
            $pparams = [':u' => $ident['username'], ':e' => $ident['email']];
            if ($input['coins'] !== null && $input['coins'] !== '') { $pfields[] = 'coins = :pc'; $pparams[':pc'] = (int)$input['coins']; }
            if ($input['level'] !== null && $input['level'] !== '') { $pfields[] = 'level = :pl'; $pparams[':pl'] = (int)$input['level']; }
            if ($input['xp'] !== null && $input['xp'] !== '') { $pfields[] = 'xp = :px'; $pparams[':px'] = (int)$input['xp']; }
            if ($input['streak'] !== null && $input['streak'] !== '') { $pfields[] = 'streak = :ps'; $pparams[':ps'] = (int)$input['streak']; }
            if ($input['username'] !== null && $input['username'] !== '') { $pfields[] = 'username = :pnu'; $pparams[':pnu'] = $input['username']; }
            if ($input['email'] !== null && $input['email'] !== '') { $pfields[] = 'email = :pne'; $pparams[':pne'] = $input['email']; }
            if (!empty($pfields)) {
                $psql = 'UPDATE player SET ' . implode(', ', $pfields) . ' WHERE username = :u OR email = :e';
                try {
                    $pstmt = $db->prepare($psql);
                    foreach ($pparams as $k => $v) { $pstmt->bindValue($k, $v); }
                    $pstmt->execute();
                } catch (Exception $ie) { /* ignore if player table/cols missing */ }
            }
        }
    } catch (Exception $ie) { /* ignore mirror errors */ }

    // Read back minimal fields to confirm persistence
    try {
        $check = $db->prepare("SELECT username, email, coins, level, xp, streak, avatar, total_games, correct_answers, wrong_answers, powerups_used FROM users WHERE id = :id LIMIT 1");
        $check->bindParam(':id', $userId);
        $check->execute();
        $row = $check->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $ie) { $row = null; }

    echo json_encode([
        'success' => true,
        'message' => 'Profile synced',
        'saved' => $row
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sync failed', 'error' => $e->getMessage()]);
}
