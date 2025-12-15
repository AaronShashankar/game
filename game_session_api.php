<?php
// game_session_api.php - Start/End sessions and update leaderboard
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();
if (!$db) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Resolve a player_id from either player_auth session or legacy users session
function resolve_player_id(PDO $db) {
  if (isset($_SESSION['player_logged']) && $_SESSION['player_logged'] && isset($_SESSION['player_id'])) {
    return (int)$_SESSION['player_id'];
  }
  // Legacy app session
  if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] && isset($_SESSION['user_id'])) {
    try {
      $uid = (int)$_SESSION['user_id'];
      $u = $db->prepare('SELECT username, email, password FROM users WHERE id = :id LIMIT 1');
      $u->execute([':id'=>$uid]);
      $row = $u->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        // Find matching player by username/email, create if missing
        $sel = $db->prepare('SELECT player_id FROM player WHERE username = :u OR email = :e LIMIT 1');
        $sel->execute([':u'=>$row['username'], ':e'=>$row['email']]);
        $p = $sel->fetch(PDO::FETCH_ASSOC);
        if ($p) return (int)$p['player_id'];
        // Create minimal player record
        $ins = $db->prepare('INSERT INTO player (fullname, username, email, password, total_score) VALUES (:f,:u,:e,:p,0)');
        $ins->execute([':f'=>$row['username'], ':u'=>$row['username'], ':e'=>$row['email'], ':p'=>$row['password']]);
        return (int)$db->lastInsertId();
      }
    } catch (Throwable $e) { /* ignore */ }
  }
  return 0;
}

function update_leaderboard(PDO $db, int $playerId, int $deltaScore) {
  // Upsert total_score and then recompute ranks
  $sel = $db->prepare('SELECT total_score FROM leaderboard WHERE player_id = :pid');
  $sel->execute([':pid'=>$playerId]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $newScore = (int)$row['total_score'] + $deltaScore;
    $upd = $db->prepare('UPDATE leaderboard SET total_score = :s WHERE player_id = :pid');
    $upd->execute([':s'=>$newScore, ':pid'=>$playerId]);
  } else {
    $ins = $db->prepare('INSERT INTO leaderboard (player_id, total_score, rank) VALUES (:pid, :s, NULL)');
    $ins->execute([':pid'=>$playerId, ':s'=>$deltaScore]);
  }
  // Recompute ranks in PHP for compatibility
  $all = $db->query('SELECT player_id, total_score FROM leaderboard ORDER BY total_score DESC, player_id ASC')->fetchAll(PDO::FETCH_ASSOC);
  $rank = 1; foreach ($all as $r) {
    $upd = $db->prepare('UPDATE leaderboard SET rank = :rk WHERE player_id = :pid');
    $upd->execute([':rk'=>$rank++, ':pid'=>$r['player_id']]);
  }
}

try {
  switch ($action) {
    case 'start_session':
      $playerId = resolve_player_id($db);
      if ($playerId <= 0) { echo json_encode(['success'=>false,'error'=>'Player authentication required']); break; }
      $mazeId = (int)($_POST['maze_id'] ?? 0);
      if ($mazeId <= 0) { echo json_encode(['success'=>false,'error'=>'maze_id required']); break; }
      $stmt = $db->prepare('INSERT INTO game_session (player_id, maze_id, start_time) VALUES (:p,:m,NOW())');
      $ok = $stmt->execute([':p'=>$playerId, ':m'=>$mazeId]);
      echo json_encode(['success'=>$ok,'session_id'=>$ok ? (int)$db->lastInsertId() : null]);
      break;

    case 'end_session':
      $playerId = resolve_player_id($db);
      if ($playerId <= 0) { echo json_encode(['success'=>false,'error'=>'Player authentication required']); break; }
      $sessionId = (int)($_POST['session_id'] ?? 0);
      $score = (int)($_POST['score_earned'] ?? 0);
      if ($sessionId <= 0) { echo json_encode(['success'=>false,'error'=>'session_id required']); break; }
      $stmt = $db->prepare('UPDATE game_session SET end_time = NOW(), score_earned = :s WHERE session_id = :sid AND player_id = :pid');
      $ok = $stmt->execute([':s'=>$score, ':sid'=>$sessionId, ':pid'=>$playerId]);
      if ($ok) { update_leaderboard($db, $playerId, $score); }
      // Also reflect on player.total_score
      if ($ok) {
        $db->prepare('UPDATE player SET total_score = total_score + :s WHERE player_id = :pid')->execute([':s'=>$score, ':pid'=>$playerId]);
      }
      echo json_encode(['success'=>$ok]);
      break;

    case 'my_sessions':
      $playerId = resolve_player_id($db);
      if ($playerId <= 0) { echo json_encode(['success'=>false,'error'=>'Player authentication required']); break; }
      $stmt = $db->prepare('SELECT session_id, maze_id, start_time, end_time, score_earned FROM game_session WHERE player_id = :p ORDER BY session_id DESC');
      $stmt->execute([':p'=>$playerId]);
      echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;

    case 'leaderboard':
      $stmt = $db->query('SELECT l.rank, l.total_score, p.player_id, p.username, p.fullname FROM leaderboard l JOIN player p ON p.player_id = l.player_id ORDER BY l.rank ASC');
      echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;

    default:
      echo json_encode(['success'=>false,'error'=>'Invalid action']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
