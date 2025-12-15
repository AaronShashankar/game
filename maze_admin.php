<?php
// maze_admin.php - Admin-protected CRUD for maze table
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();
if (!$db) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function require_admin_session_maze() {
  if (!isset($_SESSION['admin_logged']) || !$_SESSION['admin_logged'] || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success'=>false,'error'=>'Admin authentication required']);
    exit;
  }
}

try {
  switch ($action) {
    case 'list':
      $stmt = $db->query('SELECT m.maze_id, m.maze_name, m.maze_level, m.difficulty_level, m.description, m.created_by_admin_id FROM maze m ORDER BY m.maze_id DESC');
      echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;

    case 'create':
      require_admin_session_maze();
      $name = trim($_POST['maze_name'] ?? '');
      $level = isset($_POST['maze_level']) ? (int)$_POST['maze_level'] : null;
      $difficulty = trim($_POST['difficulty_level'] ?? '');
      $description = $_POST['description'] ?? null;
      if ($name === '') { echo json_encode(['success'=>false,'error'=>'maze_name required']); break; }
      $stmt = $db->prepare('INSERT INTO maze (maze_name, maze_level, difficulty_level, description, created_by_admin_id) VALUES (:n,:l,:d,:desc,:aid)');
      $ok = $stmt->execute([
        ':n'=>$name,
        ':l'=>$level,
        ':d'=>$difficulty ?: null,
        ':desc'=>$description,
        ':aid'=>(int)($_SESSION['admin_id'] ?? null)
      ]);
      echo json_encode(['success'=>$ok, 'maze_id'=>$ok ? (int)$db->lastInsertId() : null]);
      break;

    case 'update':
      require_admin_session_maze();
      $id = (int)($_POST['maze_id'] ?? 0);
      if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'maze_id required']); break; }
      $name = trim($_POST['maze_name'] ?? '');
      $level = isset($_POST['maze_level']) ? (int)$_POST['maze_level'] : null;
      $difficulty = trim($_POST['difficulty_level'] ?? '');
      $description = $_POST['description'] ?? null;
      $stmt = $db->prepare('UPDATE maze SET maze_name = :n, maze_level = :l, difficulty_level = :df, description = :ds WHERE maze_id = :id');
      $ok = $stmt->execute([':n'=>$name, ':l'=>$level, ':df'=>$difficulty ?: null, ':ds'=>$description, ':id'=>$id]);
      echo json_encode(['success'=>$ok]);
      break;

    case 'delete':
      require_admin_session_maze();
      $id = (int)($_POST['maze_id'] ?? 0);
      if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'maze_id required']); break; }
      $stmt = $db->prepare('DELETE FROM maze WHERE maze_id = :id');
      $ok = $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>$ok]);
      break;

    default:
      echo json_encode(['success'=>false,'error'=>'Invalid action']);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
