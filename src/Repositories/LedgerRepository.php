<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class LedgerRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    // Returns false if this (user, reason, ref) was already recorded — i.e.
    // the reward was already paid and must not be paid again.
    public function record(int $userId, int $coins, int $xp, string $reason, string $ref): bool
    {
        $s = $this->db->prepare('INSERT IGNORE INTO ledger (user_id, coins_delta, xp_delta, reason, ref) VALUES (?, ?, ?, ?, ?)');
        $s->execute([$userId, $coins, $xp, $reason, $ref]);
        return $s->rowCount() === 1;
    }

    public function countToday(int $userId, string $reason): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM ledger WHERE user_id = ? AND reason = ? AND created_at >= CURDATE()');
        $s->execute([$userId, $reason]);
        return (int) $s->fetchColumn();
    }

    // XP per day for the last 7 days (for the dashboard chart).
    public function xpByDay(int $userId, int $days = 7): array
    {
        $s = $this->db->prepare("SELECT DATE(created_at) AS day, SUM(xp_delta) AS xp FROM ledger
                                 WHERE user_id = ? AND reason <> 'opening_balance'
                                   AND created_at >= CURDATE() - INTERVAL ? DAY
                                 GROUP BY DATE(created_at)");
        $s->execute([$userId, $days - 1]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
