<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

// Subjects group the question bank. Players see a subject once it is marked
// visible and has at least one active question.
final class SubjectRepository
{
    // Players can pick a subject that is visible and has an active question.
    private const PLAYABLE_SQL = 's.visible = 1 AND EXISTS (SELECT 1 FROM questions pq WHERE pq.subject = s.name AND pq.active = 1)';

    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    /** Subjects on the player's picker: name, description, icon. */
    public function playable(): array
    {
        return $this->db->query('SELECT s.name, s.description, s.icon FROM subjects s WHERE ' . self::PLAYABLE_SQL . ' ORDER BY s.name')
                        ->fetchAll();
    }

    /** @return string[] */
    public function playableNames(): array
    {
        return array_column($this->playable(), 'name');
    }

    // Every subject with its question counts, for the admin panel.
    public function listWithCounts(): array
    {
        $rows = $this->db->query("SELECT s.*,
                   COUNT(q.id) AS total,
                   COALESCE(SUM(q.active), 0) AS active_count,
                   COALESCE(SUM(q.active = 1 AND q.difficulty = 'Easy'), 0)   AS easy,
                   COALESCE(SUM(q.active = 1 AND q.difficulty = 'Medium'), 0) AS medium,
                   COALESCE(SUM(q.active = 1 AND q.difficulty = 'Hard'), 0)   AS hard
            FROM subjects s LEFT JOIN questions q ON q.subject = s.name
            GROUP BY s.id ORDER BY s.name")->fetchAll();
        return array_map([self::class, 'decode'], $rows);
    }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM subjects WHERE id = ?');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ? self::decode($row) : null;
    }

    // Lookup ignores case (the column's collation), so "science" finds "Science".
    public function findByName(string $name): ?array
    {
        $s = $this->db->prepare('SELECT * FROM subjects WHERE name = ?');
        $s->execute([$name]);
        $row = $s->fetch();
        return $row ? self::decode($row) : null;
    }

    public function create(string $name, ?string $description, string $icon): int
    {
        $this->db->prepare('INSERT INTO subjects (name, description, icon, visible, updated_at) VALUES (?, ?, ?, 0, NOW())')
                 ->execute([$name, $description, $icon]);
        return (int) $this->db->lastInsertId();
    }

    // A rename reaches questions through the foreign key (ON UPDATE CASCADE).
    // Past runs store the name as text, so they're renamed here: replays,
    // challenges and the All-rounder achievement look runs up by name.
    public function update(int $id, string $oldName, string $name, ?string $description, string $icon): void
    {
        $this->db->prepare('UPDATE subjects SET name = ?, description = ?, icon = ?, updated_at = NOW() WHERE id = ?')
                 ->execute([$name, $description, $icon, $id]);
        if ($oldName !== $name) {
            $this->db->prepare('UPDATE runs SET subject = ? WHERE subject = ?')->execute([$name, $oldName]);
        }
    }

    public function setVisible(int $id, bool $visible): void
    {
        $this->db->prepare('UPDATE subjects SET visible = ?, updated_at = NOW() WHERE id = ?')->execute([(int) $visible, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
    }

    /** @return array{total: int, active: int} */
    public function questionCounts(string $name): array
    {
        $s = $this->db->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(active), 0) AS active FROM questions WHERE subject = ?');
        $s->execute([$name]);
        $row = $s->fetch();
        return ['total' => (int) $row['total'], 'active' => (int) $row['active']];
    }

    private static function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['visible'] = (bool) $row['visible'];
        foreach (['total', 'active_count', 'easy', 'medium', 'hard'] as $k) {
            if (array_key_exists($k, $row)) $row[$k] = (int) $row[$k];
        }
        return $row;
    }
}
