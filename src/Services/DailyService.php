<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RunRepository;
use App\Support\Database;

// The daily maze: one seed per day (database clock), shared by every player.
final class DailyService
{
    public function __construct(private RunRepository $runs = new RunRepository()) {}

    /** @return array{day: string, seed: int} today's maze, created on first request */
    public function today(): array
    {
        $db = Database::connection();
        $db->prepare('INSERT IGNORE INTO daily_mazes (day, seed) VALUES (CURDATE(), ?)')->execute([random_int(1, 2_147_483_647)]);
        $row = $db->query('SELECT day, seed FROM daily_mazes WHERE day = CURDATE()')->fetch();
        return ['day' => $row['day'], 'seed' => (int) $row['seed']];
    }

    // Today's maze as the dashboard shows it: your attempt and the fastest times.
    public function status(int $userId, int $limit = 5): array
    {
        $day = $this->today()['day'];
        $mine = $this->runs->findDaily($userId, $day);
        $you = null;
        if ($mine !== null) {
            $you = [
                'status'           => $mine['status'],
                'duration_seconds' => $mine['duration_seconds'] === null ? null : (int) $mine['duration_seconds'],
                'rank'             => $mine['status'] === 'completed' ? $this->runs->dailyRank($day, (int) $mine['id']) : null,
            ];
        }
        return [
            'day'         => $day,
            'difficulty'  => GameRules::DAILY_DIFFICULTY,
            'subject'     => GameRules::MIXED_SUBJECT,
            'bonus_coins' => GameRules::DAILY_BONUS_COINS,
            'finishers'   => $this->runs->dailyFinishers($day),
            'you'         => $you,
            'leaderboard' => $this->leaderboard($day, $limit),
        ];
    }

    public function leaderboard(string $day, int $limit): array
    {
        $rank = 0;
        return array_map(fn ($r) => [
            'rank'             => ++$rank,
            'username'         => $r['username'],
            'level'            => (int) $r['level'],
            'avatar'           => $r['avatar'] ?: null,
            'duration_seconds' => (int) $r['duration_seconds'],
        ], $this->runs->dailyLeaderboard($day, $limit));
    }
}
