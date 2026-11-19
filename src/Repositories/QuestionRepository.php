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

    public function subjects(): array
    {
        return $this->db->query('SELECT DISTINCT subject FROM questions WHERE active = 1 ORDER BY subject')
                        ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM questions WHERE id = ?');
        $s->execute([$id]);
        $q = $s->fetch();
        if (!$q) return null;
        $q['options'] = json_decode($q['options'], true);
        return $q;
    }

    // A random active question for the subject that hasn't been used in this
    // run yet; falls back to any question once the subject is exhausted.
    public function pickForRun(string $subject, int $runId): ?array
    {
        $subjectSql = $subject === GameRules::MIXED_SUBJECT ? '1 = 1' : 'subject = :subject';
        $params = $subject === GameRules::MIXED_SUBJECT ? [] : ['subject' => $subject];

        $s = $this->db->prepare("SELECT id FROM questions
            WHERE active = 1 AND $subjectSql
              AND id NOT IN (SELECT question_id FROM run_answers WHERE run_id = :run1)
              AND id NOT IN (SELECT question_id FROM run_gates WHERE run_id = :run2 AND question_id IS NOT NULL)
            ORDER BY RAND() LIMIT 1");
        $s->execute($params + ['run1' => $runId, 'run2' => $runId]);
        $id = $s->fetchColumn();

        if ($id === false) {
            $s = $this->db->prepare("SELECT id FROM questions WHERE active = 1 AND $subjectSql ORDER BY RAND() LIMIT 1");
            $s->execute($params);
            $id = $s->fetchColumn();
        }
        return $id === false ? null : $this->find((int) $id);
    }

    // Question as sent to the browser: never includes the answer.
    public static function publicView(array $q): array
    {
        return ['question_id' => (int) $q['id'], 'subject' => $q['subject'], 'prompt' => $q['prompt'], 'options' => $q['options']];
    }
}
