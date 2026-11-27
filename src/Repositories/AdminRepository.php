<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class AdminRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function findByEmail(string $email): ?array
    {
        $s = $this->db->prepare('SELECT * FROM admin WHERE email = ?');
        $s->execute([$email]);
        return $s->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM admin WHERE admin_id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    // Also bumps auth_version, which signs the admin out everywhere else.
    // Returns the new version.
    public function updatePassword(int $id, string $hash): int
    {
        $this->db->prepare('UPDATE admin SET password = ?, auth_version = auth_version + 1 WHERE admin_id = ?')->execute([$hash, $id]);
        $s = $this->db->prepare('SELECT auth_version FROM admin WHERE admin_id = ?');
        $s->execute([$id]);
        return (int) $s->fetchColumn();
    }
}
