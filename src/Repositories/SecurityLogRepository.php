<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class SecurityLogRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function log(string $event, ?string $username, ?string $ip, ?string $detail = null): void
    {
        $this->db->prepare('INSERT INTO security_log (event, username, ip, detail) VALUES (?, ?, ?, ?)')
                 ->execute([$event, $username, $ip, $detail === null ? null : mb_substr($detail, 0, 255)]);
    }

    public function recentFailures(string $ip, int $minutes): int
    {
        $s = $this->db->prepare("SELECT COUNT(*) FROM security_log
                                 WHERE event LIKE 'login_failed%' AND ip = ? AND created_at >= NOW() - INTERVAL ? MINUTE");
        $s->execute([$ip, $minutes]);
        return (int) $s->fetchColumn();
    }

    public function recent(int $limit): array
    {
        $s = $this->db->prepare('SELECT event, username, ip, detail, created_at AS timestamp FROM security_log ORDER BY id DESC LIMIT ?');
        $s->execute([$limit]);
        return $s->fetchAll();
    }
}
