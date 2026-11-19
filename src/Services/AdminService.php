<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SecurityLogRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;
use PDO;

// Admin panel data. Everything here sits behind the 'admin' route guard.
// (The old admin API only guarded change_admin_password; user listing,
// creation, deletion and full data export were open to anyone.)
final class AdminService
{
    private PDO $db;

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private SecurityLogRepository $log = new SecurityLogRepository(),
    ) {
        $this->db = Database::connection();
    }

    private function scalar(string $sql): mixed
    {
        return $this->db->query($sql)->fetchColumn();
    }

    public function stats(): array
    {
        return [
            'totalUsers'   => (int) $this->scalar("SELECT COUNT(*) FROM users WHERE role = 'user'"),
            'totalCoins'   => (int) $this->scalar("SELECT COALESCE(SUM(coins), 0) FROM users WHERE role = 'user'"),
            'activeUsers'  => (int) $this->scalar("SELECT COUNT(DISTINCT user_id) FROM runs WHERE started_at >= NOW() - INTERVAL 1 DAY"),
            'avgLevel'     => round((float) $this->scalar("SELECT COALESCE(AVG(level), 0) FROM users WHERE role = 'user'"), 1),
            'totalXP'      => (int) $this->scalar("SELECT COALESCE(SUM(xp), 0) FROM users WHERE role = 'user'"),
            'totalGames'   => (int) $this->scalar("SELECT COUNT(*) FROM runs WHERE status = 'completed'"),
            'totalGates'   => (int) $this->scalar("SELECT COUNT(*) FROM run_gates WHERE status <> 'pending'"),
            'totalCorrect' => (int) $this->scalar("SELECT COUNT(*) FROM run_answers WHERE correct = 1"),
        ];
    }

    public function users(): array
    {
        return $this->users->listForAdmin();
    }

    public function addUser(string $username, string $email, string $password, int $coins, int $level): void
    {
        AuthService::validateUsername($username);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw ApiError::badRequest('INVALID_EMAIL', 'Invalid email format');
        if (strlen($password) < 6) throw ApiError::badRequest('WEAK_PASSWORD', 'Password must be at least 6 characters');
        if ($coins < 0 || $coins > 1_000_000 || $level < 1 || $level > 100) {
            throw ApiError::badRequest('INVALID_INPUT', 'Coins must be 0–1,000,000 and level 1–100');
        }
        if ($this->users->usernameOrEmailTaken($username, $email)) throw ApiError::conflict('TAKEN', 'Username or email already exists');

        Database::transaction(function () use ($username, $email, $password, $coins, $level) {
            $id = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
            // Starting balance goes through the ledger like every other change.
            $this->progression->credit($id, $coins, GameRules::xpForLevel($level), 'admin_grant', 'create');
            foreach (GameRules::POWERUPS as $code) $this->shop->grant($id, $code, GameRules::STARTER_POWERUPS);
        });
    }

    public function deleteUser(int $id): void
    {
        if (!$this->users->delete($id)) throw ApiError::notFound('User not found');
    }

    // Real recent events (the old endpoint returned two hard-coded lines).
    public function activity(): array
    {
        $rows = $this->db->query("
            (SELECT 'success' AS type, CONCAT(u.username, ' completed a ', r.difficulty, ' maze (+', r.coins_earned, ' coins)') AS message, r.completed_at AS at
               FROM runs r JOIN users u ON u.id = r.user_id WHERE r.status = 'completed' ORDER BY r.completed_at DESC LIMIT 10)
            UNION ALL
            (SELECT 'info', CONCAT(username, ' registered'), created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 10)
            ORDER BY at DESC LIMIT 15")->fetchAll();
        return array_map(fn ($r) => ['type' => $r['type'], 'message' => $r['message'], 'time' => $r['at']], $rows);
    }

    public function securityLogs(): array
    {
        return array_map(fn ($r) => [
            'event' => $r['event'], 'user' => $r['username'], 'ip' => $r['ip'],
            'detail' => $r['detail'], 'timestamp' => $r['timestamp'],
        ], $this->log->recent(100));
    }

    // Computed from real data (the old endpoint returned hard-coded "75%" etc.).
    public function analytics(): array
    {
        $avgSeconds = (float) $this->scalar("SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)), 0) FROM runs WHERE status = 'completed'");
        $answers = $this->db->query('SELECT COUNT(*) AS total, COALESCE(SUM(correct), 0) AS correct FROM run_answers')->fetch();
        $popular = $this->scalar("SELECT subject FROM runs GROUP BY subject ORDER BY COUNT(*) DESC LIMIT 1");
        $started = (int) $this->scalar('SELECT COUNT(*) FROM runs');
        $completed = (int) $this->scalar("SELECT COUNT(*) FROM runs WHERE status = 'completed'");
        $hardest = $this->db->query("SELECT q.prompt, ROUND(100 * AVG(a.correct)) AS pct, COUNT(*) AS n
                                     FROM run_answers a JOIN questions q ON q.id = a.question_id
                                     GROUP BY q.id HAVING n >= 3 ORDER BY pct ASC LIMIT 5")->fetchAll();
        return [
            'avgGameDuration'  => $avgSeconds ? round($avgSeconds / 60, 1) . ' minutes' : '—',
            'successRate'      => $answers['total'] ? round(100 * $answers['correct'] / $answers['total']) . '%' : '—',
            'popularSubject'   => $popular ?: '—',
            'dailyActiveUsers' => (int) $this->scalar("SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()"),
            'completionRate'   => $started ? round(100 * $completed / $started) . '%' : '—',
            'hardestQuestions' => $hardest,
        ];
    }

    public function export(): array
    {
        return [
            'exported_at' => $this->scalar('SELECT NOW()'),
            'users' => $this->db->query("SELECT id, username, email, coins, xp, level, streak, last_checkin, is_active,
                                                last_login, login_count, total_games, correct_answers, wrong_answers, created_at
                                         FROM users WHERE role = 'user'")->fetchAll(),
            'runs' => $this->db->query('SELECT id, user_id, difficulty, subject, status, started_at, completed_at, coins_earned, xp_earned FROM runs')->fetchAll(),
            'questions' => $this->db->query('SELECT id, subject, prompt, options, answer_idx, active FROM questions')->fetchAll(),
            'items' => $this->shop->catalogue(),
        ];
    }
}
