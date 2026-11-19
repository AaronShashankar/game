<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

// Spaced-repetition boxes (question_reviews), one row per player and question
// they are still learning.
final class ReviewRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function find(int $userId, int $questionId): ?array
    {
        $s = $this->db->prepare('SELECT * FROM question_reviews WHERE user_id = ? AND question_id = ? FOR UPDATE');
        $s->execute([$userId, $questionId]);
        return $s->fetch() ?: null;
    }

    // Put (back) into box 1, due straight away.
    public function restart(int $userId, int $questionId): void
    {
        $this->db->prepare('INSERT INTO question_reviews (user_id, question_id, box, due_at) VALUES (?, ?, 1, NOW())
                            ON DUPLICATE KEY UPDATE box = 1, due_at = NOW(), updated_at = NOW()')
                 ->execute([$userId, $questionId]);
    }

    public function promote(int $userId, int $questionId, int $box, int $dueInDays): void
    {
        $this->db->prepare('UPDATE question_reviews SET box = ?, due_at = NOW() + INTERVAL ? DAY, updated_at = NOW()
                            WHERE user_id = ? AND question_id = ?')
                 ->execute([$box, $dueInDays, $userId, $questionId]);
    }

    public function remove(int $userId, int $questionId): void
    {
        $this->db->prepare('DELETE FROM question_reviews WHERE user_id = ? AND question_id = ?')->execute([$userId, $questionId]);
    }

    /** @return array{due: int, learning: int} */
    public function counts(int $userId): array
    {
        $s = $this->db->prepare('SELECT COALESCE(SUM(r.due_at <= NOW()), 0) AS due, COUNT(*) AS learning
                                 FROM question_reviews r JOIN questions q ON q.id = r.question_id
                                 WHERE r.user_id = ? AND q.active = 1');
        $s->execute([$userId]);
        $row = $s->fetch();
        return ['due' => (int) $row['due'], 'learning' => (int) $row['learning']];
    }
}
