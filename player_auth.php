<?php
// player_auth.php - Player registration/login/logout using 'player' table
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

function require_player_session() {
  if (!isset($_SESSION['player_logged']) || !$_SESSION['player_logged'] || !isset($_SESSION['player_id'])) {
    echo json_encode(['success'=>false,'error'=>'Player authentication required']);
    exit;
  }
}

try {
  switch ($action) {
    case 'register':
      $fullname = trim($_POST['fullname'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $contact  = trim($_POST['contact'] ?? '');
      $password = $_POST['password'] ?? '';
      if (!$fullname || !$email || !$username || strlen($password) < 6) { echo json_encode(['success'=>false,'error'=>'Invalid input']); break; }
      $chk = $db->prepare('SELECT player_id FROM player WHERE email = :e OR username = :u LIMIT 1');
      $chk->execute([':e'=>$email, ':u'=>$username]);
      if ($chk->fetch()) { echo json_encode(['success'=>false,'error'=>'User already exists']); break; }
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $ins = $db->prepare('INSERT INTO player (fullname, username, email, contact, password) VALUES (:f,:u,:e,:c,:p)');
      $ok = $ins->execute([':f'=>$fullname, ':u'=>$username, ':e'=>$email, ':c'=>$contact ?: null, ':p'=>$hash]);
      echo json_encode(['success'=>$ok, 'player_id'=>$ok ? (int)$db->lastInsertId() : null]);
      break;

    case 'login':
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      $stmt = $db->prepare('SELECT player_id, password, fullname, email, username, total_score FROM player WHERE username = :u OR email = :u LIMIT 1');
      $stmt->execute([':u'=>$username]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row || !password_verify($password, $row['password'])) { echo json_encode(['success'=>false,'error'=>'Invalid credentials']); break; }
      $_SESSION['player_logged'] = true; $_SESSION['player_id'] = (int)$row['player_id'];
      echo json_encode(['success'=>true,'player'=>['player_id'=>(int)$row['player_id'],'fullname'=>$row['fullname'],'email'=>$row['email'],'username'=>$row['username'],'total_score'=>(int)$row['total_score']]]);
      break;

    case 'logout':
      $_SESSION['player_logged'] = false; unset($_SESSION['player_id']);
      echo json_encode(['success'=>true]);
      break;

    case 'me':
      require_player_session();
      $id = (int)$_SESSION['player_id'];
      $stmt = $db->prepare('SELECT player_id, fullname, username, email, contact, total_score FROM player WHERE player_id = :id');
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'player'=>$stmt->fetch(PDO::FETCH_ASSOC)]);
      break;

    default:
      echo json_encode(['success'=>false,'error'=>'Invalid action']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
