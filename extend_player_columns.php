<?php
// extend_player_columns.php - Add coins, level, xp, streak to player and backfill from users
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();
if (!$db) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit; }

function colExists(PDO $db, $table, $col) {
  $stmt = $db->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :c');
  $stmt->execute([':c'=>$col]);
  return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}
function tableExists(PDO $db, $t) {
  $s=$db->prepare('SHOW TABLES LIKE :t'); $s->execute([':t'=>$t]); return (bool)$s->fetch(PDO::FETCH_NUM);
}

try {
  if (!tableExists($db, 'player')) { throw new Exception('player table not found'); }

  // Add missing columns
  $added = [];
  if (!colExists($db,'player','coins')) { $db->exec('ALTER TABLE player ADD COLUMN coins INT NOT NULL DEFAULT 0'); $added[]='coins'; }
  if (!colExists($db,'player','level')) { $db->exec('ALTER TABLE player ADD COLUMN level INT NOT NULL DEFAULT 1'); $added[]='level'; }
  if (!colExists($db,'player','xp'))    { $db->exec('ALTER TABLE player ADD COLUMN xp INT NOT NULL DEFAULT 0'); $added[]='xp'; }
  if (!colExists($db,'player','streak')){ $db->exec('ALTER TABLE player ADD COLUMN streak INT NOT NULL DEFAULT 0'); $added[]='streak'; }

  // Backfill from users when available by username/email
  if (tableExists($db,'users')) {
    $users = $db->query('SELECT id, username, email, coins, level, xp, streak FROM users WHERE role = "user"')->fetchAll(PDO::FETCH_ASSOC);
    $sel = $db->prepare('SELECT player_id FROM player WHERE username = :u OR email = :e LIMIT 1');
    $upd = $db->prepare('UPDATE player SET coins = :c, level = :l, xp = :x, streak = :s WHERE player_id = :id');
    foreach ($users as $u) {
      $sel->execute([':u'=>$u['username'], ':e'=>$u['email']]);
      $p = $sel->fetch(PDO::FETCH_ASSOC);
      if ($p) {
        $upd->execute([':c'=>(int)$u['coins'], ':l'=>(int)$u['level'], ':x'=>(int)$u['xp'], ':s'=>(int)($u['streak'] ?? 0), ':id'=>(int)$p['player_id']]);
      }
    }
  }

  echo json_encode(['success'=>true,'message'=>'Player columns ensured and backfilled','added'=>$added]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
