<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

// Weekly leaderboard prizes. There is no cron: the first request after a week
// ends settles it (top 3 by XP earned Monday–Sunday) exactly once. Only the
// week that just ended is settled; weeks with no visitors at all are skipped.
final class WeeklyRewardService
{
    private PDO $db;

    public function __construct(private ProgressionService $progression = new ProgressionService())
    {
        $this->db = Database::connection();
    }

    public function lastWeekStart(): string
    {
        return (string) $this->db->query('SELECT CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY - INTERVAL 7 DAY')->fetchColumn();
    }

    public function settleLastWeek(): void
    {
        $week = $this->lastWeekStart();
        $check = $this->db->prepare('SELECT 1 FROM weekly_settlements WHERE week_start = ?');
        $check->execute([$week]);
        if ($check->fetchColumn()) return;

        Database::transaction(function () use ($week) {
            // The primary key makes this the lock: only one request wins the insert.
            $claim = $this->db->prepare('INSERT IGNORE INTO weekly_settlements (week_start) VALUES (?)');
            $claim->execute([$week]);
            if ($claim->rowCount() === 0) return;

            $s = $this->db->prepare("SELECT l.user_id, SUM(l.xp_delta) AS xp
                FROM ledger l JOIN users u ON u.id = l.user_id
                WHERE l.created_at >= ? AND l.created_at < ? + INTERVAL 7 DAY
                  AND l.reason NOT IN ('opening_balance', 'admin_grant')
                  AND u.role = 'user' AND u.is_active = 1
                GROUP BY l.user_id HAVING xp > 0
                ORDER BY xp DESC, l.user_id ASC LIMIT " . count(GameRules::WEEKLY_PRIZES));
            $s->execute([$week, $week]);

            $ins = $this->db->prepare('INSERT INTO weekly_rewards (week_start, `rank`, user_id, xp, coins) VALUES (?, ?, ?, ?, ?)');
            foreach ($s->fetchAll() as $i => $row) {
                $rank = $i + 1;
                $coins = GameRules::WEEKLY_PRIZES[$rank];
                $ins->execute([$week, $rank, $row['user_id'], $row['xp'], $coins]);
                $this->progression->credit((int) $row['user_id'], $coins, 0, 'weekly_prize', $week);
            }
        });
    }

    // Last week's winners, for the dashboard.
    public function lastWeek(?int $userId = null): array
    {
        $week = $this->lastWeekStart();
        $s = $this->db->prepare('SELECT w.`rank`, w.user_id, w.xp, w.coins, u.username
                                 FROM weekly_rewards w JOIN users u ON u.id = w.user_id
                                 WHERE w.week_start = ? ORDER BY w.`rank`');
        $s->execute([$week]);
        $winners = $s->fetchAll();
        $mine = null;
        foreach ($winners as $w) {
            if ($userId !== null && (int) $w['user_id'] === $userId) $mine = ['rank' => (int) $w['rank'], 'coins' => (int) $w['coins']];
        }
        return [
            'week_start' => $week,
            'prizes'     => array_values(GameRules::WEEKLY_PRIZES),
            'winners'    => array_map(fn ($w) => [
                'rank' => (int) $w['rank'], 'username' => $w['username'], 'xp' => (int) $w['xp'], 'coins' => (int) $w['coins'],
            ], $winners),
            'you'        => $mine,
        ];
    }
}
