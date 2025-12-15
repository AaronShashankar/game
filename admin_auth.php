<?php
// admin_auth.php - Admin registration/login/logout using 'admin' table
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

function require_admin_session() {
  if (!isset($_SESSION['admin_logged']) || !$_SESSION['admin_logged'] || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success'=>false,'error'=>'Admin authentication required']);
    exit;
  }
}

try {
  switch ($action) {
    case 'register':
      // One-time or repeated safe register
      $fullname = trim($_POST['fullname'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      if (!$fullname || !$email || !$username || strlen($password) < 8) {
        echo json_encode(['success'=>false,'error'=>'Invalid input']); break;
      }
      $chk = $db->prepare('SELECT admin_id FROM admin WHERE email = :e OR username = :u LIMIT 1');
      $chk->execute([':e'=>$email, ':u'=>$username]);
      if ($chk->fetch()) { echo json_encode(['success'=>false,'error'=>'Admin already exists']); break; }
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $ins = $db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES (:f,:e,:u,:p)');
      $ok = $ins->execute([':f'=>$fullname, ':e'=>$email, ':u'=>$username, ':p'=>$hash]);
      echo json_encode(['success'=>$ok]);
      break;

    case 'login':
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      $stmt = $db->prepare('SELECT admin_id, password, fullname, email, username FROM admin WHERE username = :u OR email = :u LIMIT 1');
      $stmt->execute([':u'=>$username]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row || !password_verify($password, $row['password'])) {
        echo json_encode(['success'=>false,'error'=>'Invalid credentials']); break;
      }
      $_SESSION['admin_logged'] = true;
      $_SESSION['admin_id'] = (int)$row['admin_id'];
      echo json_encode(['success'=>true,'admin'=>['admin_id'=>(int)$row['admin_id'],'fullname'=>$row['fullname'],'email'=>$row['email'],'username'=>$row['username']]]);
      break;

    case 'logout':
      $_SESSION['admin_logged'] = false; unset($_SESSION['admin_id']);
      echo json_encode(['success'=>true]);
      break;

    case 'me':
      require_admin_session();
      $id = (int)$_SESSION['admin_id'];
      $stmt = $db->prepare('SELECT admin_id, fullname, email, username FROM admin WHERE admin_id = :id');
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'admin'=>$stmt->fetch(PDO::FETCH_ASSOC)]);
      break;

    default:
      echo json_encode(['success'=>false,'error'=>'Invalid action']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
