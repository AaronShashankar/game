<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\GameRules;
use App\Support\Database;
use PDO;

final class QuestionRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM questions WHERE id = ?');
        $s->execute([$id]);
        $q = $s->fetch();
        return $q ? self::decode($q) : null;
    }

    // ---- Picking a question for a gate ------------------------------------------
    // All pickers skip questions already served in this run, and fall back to
    // any active question in the subject once those run out.

    // Normal runs: a random question, preferring ones tagged with the maze's difficulty.
    public function pickRandom(string $subject, string $difficulty, int $runId): ?array
    {
        return $this->pick($subject, $runId, 'ORDER BY (q.difficulty = :difficulty) DESC, RAND()', ['difficulty' => $difficulty]);
    }

    // Daily maze: the order is a hash of the day's seed, so every player meets
    // the same questions in the same order.
    public function pickSeeded(string $subject, string $difficulty, int $runId, int $seed): ?array
    {
        return $this->pick($subject, $runId, "ORDER BY (q.difficulty = :difficulty) DESC, MD5(CONCAT(:seed, ':', q.id))",
                           ['difficulty' => $difficulty, 'seed' => $seed]);
    }

    // The oldest due review question for this player that fits the run.
    public function pickDueReview(int $userId, string $subject, int $runId): ?array
    {
        [$subjectSql, $params] = $this->subjectFilter($subject);
        $s = $this->db->prepare("SELECT q.* FROM question_reviews r JOIN questions q ON q.id = r.question_id
            WHERE r.user_id = :user AND r.due_at <= NOW() AND q.active = 1 AND $subjectSql AND {$this->notUsedSql()}
            ORDER BY r.due_at LIMIT 1");
        $s->execute($params + ['user' => $userId, 'run1' => $runId, 'run2' => $runId]);
        $q = $s->fetch();
        return $q ? self::decode($q) : null;
    }

    private function pick(string $subject, int $runId, string $orderSql, array $orderParams): ?array
    {
        [$subjectSql, $params] = $this->subjectFilter($subject);
        $s = $this->db->prepare("SELECT q.* FROM questions q WHERE q.active = 1 AND $subjectSql AND {$this->notUsedSql()} $orderSql LIMIT 1");
        $s->execute($params + $orderParams + ['run1' => $runId, 'run2' => $runId]);
        $q = $s->fetch();
        if ($q === false) {
            $s = $this->db->prepare("SELECT q.* FROM questions q WHERE q.active = 1 AND $subjectSql ORDER BY RAND() LIMIT 1");
            $s->execute($params);
            $q = $s->fetch();
        }
        return $q ? self::decode($q) : null;
    }

    // Mixed mode (and the daily maze) draw from every subject players can see,
    // so questions in a hidden, still-being-written subject never leak into play.
    private function subjectFilter(string $subject): array
    {
        return $subject === GameRules::MIXED_SUBJECT
            ? ['q.subject IN (SELECT name FROM subjects WHERE visible = 1)', []]
            : ['q.subject = :subject', ['subject' => $subject]];
    }

    private function notUsedSql(): string
    {
        return 'q.id NOT IN (SELECT question_id FROM run_answers WHERE run_id = :run1)
            AND q.id NOT IN (SELECT question_id FROM run_gates WHERE run_id = :run2 AND question_id IS NOT NULL)';
    }

    // ---- Learning stats ------------------------------------------------------------

    // Per subject: answers given, how many were right, and how many distinct
    // questions the player has answered correctly at least once.
    public function masteryFor(int $userId): array
    {
        $s = $this->db->prepare('SELECT q.subject,
                   COUNT(*) AS answered, SUM(a.correct) AS correct,
                   COUNT(DISTINCT CASE WHEN a.correct = 1 THEN q.id END) AS known
            FROM run_answers a JOIN runs r ON r.id = a.run_id JOIN questions q ON q.id = a.question_id
            WHERE r.user_id = ? GROUP BY q.subject');
        $s->execute([$userId]);
        $mine = [];
        foreach ($s->fetchAll() as $row) $mine[$row['subject']] = $row;

        $totals = $this->db->query('SELECT q.subject, COUNT(*) FROM questions q JOIN subjects s ON s.name = q.subject
                                    WHERE q.active = 1 AND s.visible = 1 GROUP BY q.subject ORDER BY q.subject')
                           ->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach ($totals as $subject => $total) {
            $m = $mine[$subject] ?? ['answered' => 0, 'correct' => 0, 'known' => 0];
            $known = min((int) $m['known'], (int) $total);
            $out[] = [
                'subject'  => $subject,
                'answered' => (int) $m['answered'],
                'correct'  => (int) $m['correct'],
                'accuracy' => $m['answered'] ? (int) round(100 * $m['correct'] / $m['answered']) : null,
                'known'    => $known,
                'total'    => (int) $total,
                'mastery'  => $total ? (int) floor(100 * $known / $total) : 0,
            ];
        }
        return $out;
    }

    // ---- Admin -------------------------------------------------------------------

    // Filtered list with answer stats, newest first.
    public function search(array $f, int $limit = 500): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($f['subject'] ?? '') !== '')    { $where[] = 'q.subject = :subject';       $params['subject'] = $f['subject']; }
        if (($f['difficulty'] ?? '') !== '') { $where[] = 'q.difficulty = :difficulty'; $params['difficulty'] = $f['difficulty']; }
        if (($f['status'] ?? '') === 'active')   $where[] = 'q.active = 1';
        if (($f['status'] ?? '') === 'archived') $where[] = 'q.active = 0';
        if (($f['q'] ?? '') !== '') { $where[] = 'q.prompt LIKE :q'; $params['q'] = '%' . addcslashes($f['q'], '%_\\') . '%'; }

        $s = $this->db->prepare('SELECT q.*, COUNT(a.question_id) AS times_answered,
                   ROUND(100 * AVG(a.correct)) AS pct_correct
            FROM questions q LEFT JOIN run_answers a ON a.question_id = q.id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY q.id ORDER BY q.id DESC LIMIT ' . (int) $limit);
        $s->execute($params);
        return array_map(function ($q) {
            $q = self::decode($q);
            $q['times_answered'] = (int) $q['times_answered'];
            $q['pct_correct'] = $q['pct_correct'] === null ? null : (int) $q['pct_correct'];
            return $q;
        }, $s->fetchAll());
    }

    public function create(array $q): int
    {
        $this->db->prepare('INSERT INTO questions (subject, difficulty, prompt, options, answer_idx, explanation, active, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                 ->execute([$q['subject'], $q['difficulty'], $q['prompt'], json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                            $q['answer_idx'], $q['explanation'], (int) $q['active']]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $q): bool
    {
        $s = $this->db->prepare('UPDATE questions SET subject = ?, difficulty = ?, prompt = ?, options = ?, answer_idx = ?,
                                        explanation = ?, active = ?, updated_at = NOW() WHERE id = ?');
        $s->execute([$q['subject'], $q['difficulty'], $q['prompt'], json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                     $q['answer_idx'], $q['explanation'], (int) $q['active'], $id]);
        return $s->rowCount() === 1 || $this->find($id) !== null;
    }

    public function setActive(int $id, bool $active): bool
    {
        $s = $this->db->prepare('UPDATE questions SET active = ?, updated_at = NOW() WHERE id = ?');
        $s->execute([(int) $active, $id]);
        return $s->rowCount() === 1;
    }

    // Deletes the questions and returns how many went. A gate that is waiting
    // on one of them in an unfinished run is reset, so the player is simply
    // served a different question. Past answers stay (run_answers has no
    // foreign key); reviews go with the question (ON DELETE CASCADE).
    public function deleteMany(array $ids): int
    {
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $this->db->prepare("UPDATE run_gates SET question_id = NULL, served_at = NULL
                            WHERE question_id IN ($in) AND status = 'pending'")->execute($ids);
        $s = $this->db->prepare("DELETE FROM questions WHERE id IN ($in)");
        $s->execute($ids);
        return $s->rowCount();
    }

    public function existsInSubject(string $subject, string $prompt): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM questions WHERE subject = ? AND prompt = ? LIMIT 1');
        $s->execute([$subject, $prompt]);
        return (bool) $s->fetchColumn();
    }

    private static function decode(array $q): array
    {
        $q['id'] = (int) $q['id'];
        $q['answer_idx'] = (int) $q['answer_idx'];
        $q['active'] = (bool) $q['active'];
        $q['options'] = is_string($q['options']) ? json_decode($q['options'], true) : $q['options'];
        return $q;
    }

    // Question as sent to the browser while it's being asked: never includes the answer.
    public static function publicView(array $q): array
    {
        return ['question_id' => (int) $q['id'], 'subject' => $q['subject'], 'difficulty' => $q['difficulty'] ?? null,
                'prompt' => $q['prompt'], 'options' => $q['options']];
    }
}
