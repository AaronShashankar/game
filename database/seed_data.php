<?php
// seed_data.php - Insert sample admin, players, mazes
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();
if (!$db) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit; }

try {
  $db->beginTransaction();
  // Admin
  $chk = $db->query("SELECT COUNT(*) FROM admin")->fetchColumn();
  if ((int)$chk === 0) {
    $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES ("Super Admin","admin@example.com","admin",:p)')->execute([':p'=>$hash]);
  }

  // Players
  $chk = $db->query("SELECT COUNT(*) FROM player")->fetchColumn();
  if ((int)$chk === 0) {
    $players = [
      ['Alice','alice','alice@example.com','9800000001','Pass@123'],
      ['Bob','bob','bob@example.com','9800000002','Pass@123'],
      ['Charlie','charlie','charlie@example.com','9800000003','Pass@123']
    ];
    $stmt = $db->prepare('INSERT INTO player (fullname, username, email, contact, password) VALUES (:f,:u,:e,:c,:p)');
    foreach ($players as $p) {
      $stmt->execute([':f'=>$p[0],':u'=>$p[1],':e'=>$p[2],':c'=>$p[3],':p'=>password_hash($p[4], PASSWORD_DEFAULT)]);
    }
  }

  // Mazes
  $chk = $db->query("SELECT COUNT(*) FROM maze")->fetchColumn();
  if ((int)$chk === 0) {
    $adminId = (int)$db->query('SELECT admin_id FROM admin LIMIT 1')->fetchColumn();
    $mazes = [
      ['Beginner Maze',1,'easy','Simple intro maze'],$adminId,
      ['Intermediate Maze',2,'medium','More paths'],$adminId,
      ['Pro Maze',3,'hard','Challenging'],$adminId
    ];
    $stmt = $db->prepare('INSERT INTO maze (maze_name, maze_level, difficulty_level, description, created_by_admin_id) VALUES (:n,:l,:d,:ds,:aid)');
    // Expand the array into triplets
    $data = [
      ['Beginner Maze',1,'easy','Simple intro maze',$adminId],
      ['Intermediate Maze',2,'medium','More paths',$adminId],
      ['Pro Maze',3,'hard','Challenging',$adminId]
    ];
    foreach ($data as $m) {
      $stmt->execute([':n'=>$m[0],':l'=>$m[1],':d'=>$m[2],':ds'=>$m[3],':aid'=>$m[4]]);
    }
  }

  $db->commit();
  echo json_encode(['success'=>true,'message'=>'Seed completed']);
} catch (Throwable $e) {
  if ($db->inTransaction()) { $db->rollBack(); }
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
