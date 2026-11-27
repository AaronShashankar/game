<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function find(int $id, bool $forUpdate = false): ?array
    {
        $s = $this->db->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'" . ($forUpdate ? ' FOR UPDATE' : ''));
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $s = $this->db->prepare("SELECT * FROM users WHERE email = ? AND role = 'user'");
        $s->execute([$email]);
        return $s->fetch() ?: null;
    }

    public function usernameOrEmailTaken(string $username, string $email, ?int $exceptId = null): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        $s->execute([$username, $email, $exceptId ?? 0]);
        return (bool) $s->fetchColumn();
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $this->db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')")
                 ->execute([$username, $email, $passwordHash]);
        return (int) $this->db->lastInsertId();
    }

    public function recordLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?')->execute([$id]);
    }

    public function updateProfile(int $id, string $username, string $email, ?string $avatar): void
    {
        $this->db->prepare('UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?')
                 ->execute([$username, $email, $avatar, $id]);
    }

    // Also bumps auth_version, which signs the player out everywhere else.
    // Returns the new version.
    public function updatePassword(int $id, string $hash): int
    {
        $this->db->prepare('UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?')->execute([$hash, $id]);
        $s = $this->db->prepare('SELECT auth_version FROM users WHERE id = ?');
        $s->execute([$id]);
        return (int) $s->fetchColumn();
    }

    // Balance changes. Only ProgressionService calls these.
    public function addBalance(int $id, int $coins, int $xp, int $level): void
    {
        $this->db->prepare('UPDATE users SET coins = coins + ?, xp = xp + ?, level = ? WHERE id = ?')
                 ->execute([$coins, $xp, $level, $id]);
    }

    // Atomic spend: fails (returns false) instead of going negative.
    public function spendCoins(int $id, int $coins): bool
    {
        $s = $this->db->prepare('UPDATE users SET coins = coins - ? WHERE id = ? AND coins >= ?');
        $s->execute([$coins, $id, $coins]);
        return $s->rowCount() === 1;
    }

    public function incrementStat(int $id, string $column, int $by = 1): void
    {
        $allowed = ['total_games', 'correct_answers', 'wrong_answers', 'powerups_used'];
        if (!in_array($column, $allowed, true)) throw new \InvalidArgumentException($column);
        $this->db->prepare("UPDATE users SET `$column` = `$column` + ? WHERE id = ?")->execute([$by, $id]);
    }

    public function setCheckin(int $id, string $date, int $streak): void
    {
        $this->db->prepare('UPDATE users SET last_checkin = ?, streak = ? WHERE id = ?')->execute([$date, $streak, $id]);
    }

    /** @return array<string,string> achievement code => unlocked_at */
    public function achievements(int $id): array
    {
        $s = $this->db->prepare('SELECT code, unlocked_at FROM user_achievements WHERE user_id = ? ORDER BY unlocked_at, code');
        $s->execute([$id]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Returns true if the achievement was newly unlocked.
    public function unlockAchievement(int $id, string $code): bool
    {
        $s = $this->db->prepare('INSERT IGNORE INTO user_achievements (user_id, code) VALUES (?, ?)');
        $s->execute([$id, $code]);
        return $s->rowCount() === 1;
    }

    /** @param 'skin'|'theme' $kind */
    public function equip(int $id, string $kind, ?string $code): void
    {
        $column = $kind === 'skin' ? 'equipped_skin' : 'equipped_theme';
        $this->db->prepare("UPDATE users SET $column = ? WHERE id = ?")->execute([$code, $id]);
    }

    /** @param 'xp'|'coins'|'level'|'streak' $orderBy */
    public function leaderboard(string $orderBy, int $limit): array
    {
        $column = match ($orderBy) { 'coins' => 'coins', 'streak' => 'streak', default => 'xp' };
        $s = $this->db->prepare("SELECT id, username, level, xp, coins, streak, avatar, total_games
                                 FROM users WHERE role = 'user' AND is_active = 1
                                 ORDER BY $column DESC, xp DESC, id ASC LIMIT ?");
        $s->execute([$limit]);
        return $s->fetchAll();
    }

    // XP earned since Monday of the current week (database clock), from the ledger.
    // Balances carried over or granted by an admin don't count as earned.
    public function weeklyLeaderboard(int $limit): array
    {
        $s = $this->db->prepare("SELECT u.id, u.username, u.level, u.coins, u.streak, u.avatar, u.total_games,
                                        SUM(l.xp_delta) AS xp
                                 FROM ledger l JOIN users u ON u.id = l.user_id
                                 WHERE l.created_at >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY
                                   AND l.reason NOT IN ('opening_balance', 'admin_grant')
                                   AND u.role = 'user' AND u.is_active = 1
                                 GROUP BY u.id HAVING xp > 0
                                 ORDER BY xp DESC, u.id ASC LIMIT ?");
        $s->execute([$limit]);
        return $s->fetchAll();
    }

    public function listForAdmin(): array
    {
        return $this->db->query("SELECT id, username, email, level, coins, xp, streak, is_active, total_games,
                                        powerups_used, correct_answers, wrong_answers,
                                        COALESCE(last_login, created_at) AS last_active, created_at
                                 FROM users WHERE role = 'user' ORDER BY id DESC")->fetchAll();
    }

    public function delete(int $id): bool
    {
        $s = $this->db->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        $s->execute([$id]);
        return $s->rowCount() === 1;
    }
}
