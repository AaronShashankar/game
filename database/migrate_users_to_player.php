<?php
// migrate_users_to_player.php - Copy existing users/admin into player/admin + leaderboard
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();
if (!$db) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit; }

function tableExists(PDO $db, $table) {
  try { $s=$db->prepare('SHOW TABLES LIKE :t'); $s->execute([':t'=>$table]); return (bool)$s->fetch(PDO::FETCH_NUM); } catch(Throwable $e){ return false; }
}

try {
  if (!tableExists($db, 'users')) { throw new Exception('users table not found'); }
  if (!tableExists($db, 'player')) { throw new Exception('player table not found'); }
  if (!tableExists($db, 'admin')) { throw new Exception('admin table not found'); }
  if (!tableExists($db, 'leaderboard')) { throw new Exception('leaderboard table not found'); }

  $db->beginTransaction();

  // Migrate admins from users.role='admin' to admin table (skip if username/email exists)
  $admins = $db->query("SELECT id, username, email, password FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_ASSOC);
  $insAdmin = $db->prepare('INSERT INTO admin (fullname, email, username, password) VALUES (:f,:e,:u,:p)');
  foreach ($admins as $a) {
    $exists = $db->prepare('SELECT admin_id FROM admin WHERE email = :e OR username = :u LIMIT 1');
    $exists->execute([':e'=>$a['email'], ':u'=>$a['username']]);
    if (!$exists->fetch()) {
      $fullname = $a['username'];
      $insAdmin->execute([':f'=>$fullname, ':e'=>$a['email'], ':u'=>$a['username'], ':p'=>$a['password']]);
    }
  }

  // Migrate users to player table
  $users = $db->query("SELECT id, username, email, password, xp, coins FROM users WHERE role='user'")->fetchAll(PDO::FETCH_ASSOC);
  $insPlayer = $db->prepare('INSERT INTO player (fullname, username, email, contact, password, total_score) VALUES (:f,:u,:e,:c,:p,:s)');
  $selPlayer = $db->prepare('SELECT player_id, total_score FROM player WHERE username = :u OR email = :e LIMIT 1');
  $upPlayerScore = $db->prepare('UPDATE player SET total_score = :s WHERE player_id = :id');
  $upsertLBsel = $db->prepare('SELECT leaderboard_id, total_score FROM leaderboard WHERE player_id = :pid');
  $lbUpdate = $db->prepare('UPDATE leaderboard SET total_score = :s WHERE player_id = :pid');
  $lbInsert = $db->prepare('INSERT INTO leaderboard (player_id, total_score, rank) VALUES (:pid, :s, NULL)');

  foreach ($users as $u) {
    $fullname = $u['username'];
    $score = (int)($u['xp'] ?? 0);
    if ($score <= 0) { $score = (int)($u['coins'] ?? 0); }

    $selPlayer->execute([':u'=>$u['username'], ':e'=>$u['email']]);
    $existing = $selPlayer->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
      // Update total_score if higher
      $pid = (int)$existing['player_id'];
      $current = (int)$existing['total_score'];
      if ($score > $current) { $upPlayerScore->execute([':s'=>$score, ':id'=>$pid]); }
    } else {
      $insPlayer->execute([':f'=>$fullname, ':u'=>$u['username'], ':e'=>$u['email'], ':c'=>null, ':p'=>$u['password'], ':s'=>$score]);
      $pid = (int)$db->lastInsertId();
    }

    // Upsert leaderboard
    if (!empty($pid)) {
      $upsertLBsel->execute([':pid'=>$pid]);
      $lb = $upsertLBsel->fetch(PDO::FETCH_ASSOC);
      if ($lb) {
        $lbUpdate->execute([':s'=>$score, ':pid'=>$pid]);
      } else {
        $lbInsert->execute([':pid'=>$pid, ':s'=>$score]);
      }
    }
  }

  // Recompute ranks
  $rows = $db->query('SELECT player_id, total_score FROM leaderboard ORDER BY total_score DESC, player_id ASC')->fetchAll(PDO::FETCH_ASSOC);
  $rank = 1; foreach ($rows as $r) {
    $db->prepare('UPDATE leaderboard SET rank = :rk WHERE player_id = :pid')->execute([':rk'=>$rank++, ':pid'=>$r['player_id']]);
  }

  $db->commit();
  echo json_encode(['success'=>true,'message'=>'Migration users->player/admin complete']);
} catch (Throwable $e) {
  if ($db && $db->inTransaction()) { $db->rollBack(); }
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
