<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

// Items catalogue + per-player inventory.
final class ShopRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function catalogue(): array
    {
        $rows = $this->db->query('SELECT code, name, description, icon, kind, price, active FROM items ORDER BY sort_order')->fetchAll();
        foreach ($rows as &$r) { $r['price'] = (int) $r['price']; $r['active'] = (bool) $r['active']; }
        return $rows;
    }

    public function item(string $code): ?array
    {
        $s = $this->db->prepare('SELECT * FROM items WHERE code = ?');
        $s->execute([$code]);
        $item = $s->fetch();
        if (!$item) return null;
        $item['grants'] = json_decode($item['grants'], true);
        return $item;
    }

    /** @return array<string,int> item_code => qty */
    public function inventory(int $userId): array
    {
        $s = $this->db->prepare('SELECT item_code, qty FROM inventory WHERE user_id = ? AND qty > 0');
        $s->execute([$userId]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function grant(int $userId, string $code, int $qty): void
    {
        $this->db->prepare('INSERT INTO inventory (user_id, item_code, qty) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)')
                 ->execute([$userId, $code, $qty]);
    }

    // Atomically use one; false if the player has none.
    public function consume(int $userId, string $code): bool
    {
        $s = $this->db->prepare('UPDATE inventory SET qty = qty - 1 WHERE user_id = ? AND item_code = ? AND qty > 0');
        $s->execute([$userId, $code]);
        return $s->rowCount() === 1;
    }
}
