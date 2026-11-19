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

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->prepare('UPDATE admin SET password = ? WHERE admin_id = ?')->execute([$hash, $id]);
    }
}
