<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class RunRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    /** @param array{mode?: string, daily_date?: ?string, challenge_of?: ?int, pickup_count?: int} $extra */
    public function create(int $userId, string $difficulty, string $subject, int $seed, int $gateCount, ?int $timeLimit, array $extra = []): int
    {
        $this->db->prepare('INSERT INTO runs (user_id, mode, daily_date, challenge_of, pickup_count, difficulty, subject, seed, gate_count, started_at, deadline_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), IF(? IS NULL, NULL, NOW() + INTERVAL ? SECOND))')
                 ->execute([$userId, $extra['mode'] ?? 'normal', $extra['daily_date'] ?? null, $extra['challenge_of'] ?? null,
                            $extra['pickup_count'] ?? 0, $difficulty, $subject, $seed, $gateCount, $timeLimit, $timeLimit ?? 0]);
        $runId = (int) $this->db->lastInsertId();

        $ins = $this->db->prepare('INSERT INTO run_gates (run_id, gate_no) VALUES (?, ?)');
        for ($n = 1; $n <= $gateCount; $n++) $ins->execute([$runId, $n]);
        return $runId;
    }

    // Starting a new maze abandons the previous one. The daily maze is left
    // alone so it can still be resumed later that day.
    public function abandonActive(int $userId): void
    {
        $this->db->prepare("UPDATE runs SET status = 'abandoned' WHERE user_id = ? AND status = 'active' AND mode <> 'daily'")->execute([$userId]);
    }

    public function findDaily(int $userId, string $day): ?array
    {
        $s = $this->db->prepare('SELECT id FROM runs WHERE user_id = ? AND daily_date = ?');
        $s->execute([$userId, $day]);
        $id = $s->fetchColumn();
        return $id === false ? null : $this->find((int) $id, $userId);
    }

    // A completed run that can be challenged, with its player's name.
    public function findByShareCode(string $code): ?array
    {
        $s = $this->db->prepare("SELECT r.*, u.username FROM runs r JOIN users u ON u.id = r.user_id
                                 WHERE r.share_code = ? AND r.status = 'completed' AND u.is_active = 1");
        $s->execute([$code]);
        return $s->fetch() ?: null;
    }

    public function findById(int $runId): ?array
    {
        $s = $this->db->prepare('SELECT r.*, u.username FROM runs r JOIN users u ON u.id = r.user_id WHERE r.id = ?');
        $s->execute([$runId]);
        return $s->fetch() ?: null;
    }

    public function setShareCode(int $runId, string $code): bool
    {
        $s = $this->db->prepare('UPDATE IGNORE runs SET share_code = ? WHERE id = ? AND share_code IS NULL');
        $s->execute([$code, $runId]);
        return $s->rowCount() === 1;
    }

    // The question the challenged run was asked at this gate and attempt, if any.
    public function sourceQuestionId(int $sourceRunId, int $gateNo, int $attemptNo): ?int
    {
        $s = $this->db->prepare('SELECT question_id FROM run_answers WHERE run_id = ? AND gate_no = ? AND attempt_no = ?');
        $s->execute([$sourceRunId, $gateNo, $attemptNo]);
        $id = $s->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    // Marks pickup N as collected; false if it already was.
    public function collectPickup(int $runId, int $pickupNo): bool
    {
        $bit = 1 << ($pickupNo - 1);
        $s = $this->db->prepare('UPDATE runs SET pickups_mask = pickups_mask | ? WHERE id = ? AND (pickups_mask & ?) = 0');
        $s->execute([$bit, $runId, $bit]);
        return $s->rowCount() === 1;
    }

    public function noteReviewServed(int $runId): void
    {
        $this->db->prepare('UPDATE runs SET reviews_served = reviews_served + 1 WHERE id = ?')->execute([$runId]);
    }

    /** Every answer in the run with its question, in the order given. */
    public function answersWithQuestions(int $runId): array
    {
        $s = $this->db->prepare('SELECT a.gate_no, a.attempt_no, a.chosen_idx, a.correct, q.prompt, q.options, q.answer_idx, q.explanation
                                 FROM run_answers a JOIN questions q ON q.id = a.question_id
                                 WHERE a.run_id = ? ORDER BY a.created_at, a.gate_no, a.attempt_no');
        $s->execute([$runId]);
        return $s->fetchAll();
    }

    public function completedSubjects(int $userId): array
    {
        $s = $this->db->prepare("SELECT DISTINCT subject FROM runs WHERE user_id = ? AND status = 'completed'");
        $s->execute([$userId]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fastest finishes of a day's maze (ties go to whoever finished first).
    public function dailyLeaderboard(string $day, int $limit): array
    {
        $s = $this->db->prepare("SELECT r.id, r.user_id, r.duration_seconds, u.username, u.level, u.avatar
                                 FROM runs r JOIN users u ON u.id = r.user_id
                                 WHERE r.daily_date = ? AND r.status = 'completed' AND u.is_active = 1
                                 ORDER BY r.duration_seconds ASC, r.completed_at ASC, r.id ASC LIMIT ?");
        $s->execute([$day, $limit]);
        return $s->fetchAll();
    }

    public function dailyRank(string $day, int $runId): ?int
    {
        $s = $this->db->prepare("SELECT 1 + COUNT(*) FROM runs o JOIN runs me ON me.id = ?
                                 JOIN users u ON u.id = o.user_id AND u.is_active = 1
                                 WHERE o.daily_date = ? AND o.status = 'completed' AND me.status = 'completed'
                                   AND (o.duration_seconds < me.duration_seconds
                                        OR (o.duration_seconds = me.duration_seconds AND (o.completed_at < me.completed_at
                                            OR (o.completed_at = me.completed_at AND o.id < me.id))))");
        $s->execute([$runId, $day]);
        return (int) $s->fetchColumn() ?: null;
    }

    public function dailyFinishers(string $day): int
    {
        $s = $this->db->prepare("SELECT COUNT(*) FROM runs WHERE daily_date = ? AND status = 'completed'");
        $s->execute([$day]);
        return (int) $s->fetchColumn();
    }

    // Seconds until the deadline / since start are computed by the database so
    // PHP and MySQL clocks/timezones can never disagree.
    public function find(int $runId, int $userId, bool $forUpdate = false): ?array
    {
        $s = $this->db->prepare('SELECT r.*,
                TIMESTAMPDIFF(SECOND, NOW(), r.deadline_at) AS seconds_left,
                TIMESTAMPDIFF(SECOND, r.started_at, NOW()) AS elapsed,
                (r.freeze_until IS NOT NULL AND r.freeze_until > NOW()) AS frozen,
                GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), r.freeze_until), 0)) AS freeze_left
            FROM runs r WHERE r.id = ? AND r.user_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
        $s->execute([$runId, $userId]);
        return $s->fetch() ?: null;
    }

    public function update(int $runId, array $fields): void
    {
        $sets = implode(', ', array_map(fn ($k) => "`$k` = ?", array_keys($fields)));
        $this->db->prepare("UPDATE runs SET $sets WHERE id = ?")->execute([...array_values($fields), $runId]);
    }

    public function extendDeadline(int $runId, int $seconds): void
    {
        $this->db->prepare('UPDATE runs SET deadline_at = deadline_at + INTERVAL ? SECOND,
                                            freeze_until = NOW() + INTERVAL ? SECOND WHERE id = ?')
                 ->execute([$seconds, $seconds, $runId]);
    }

    public function resetDeadline(int $runId, int $seconds): void
    {
        $this->db->prepare('UPDATE runs SET deadline_at = NOW() + INTERVAL ? SECOND, freeze_until = NULL, combo = 0 WHERE id = ?')
                 ->execute([$seconds, $runId]);
    }

    public function complete(int $runId, int $coins, int $xp): void
    {
        $this->db->prepare("UPDATE runs SET status = 'completed', completed_at = NOW(),
                                            duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                                            coins_earned = coins_earned + ?, xp_earned = xp_earned + ? WHERE id = ?")
                 ->execute([$coins, $xp, $runId]);
    }

    public function gate(int $runId, int $gateNo, bool $forUpdate = false): ?array
    {
        $s = $this->db->prepare('SELECT g.*, TIMESTAMPDIFF(MICROSECOND, g.served_at, NOW(3)) / 1000000 AS seconds_since_served
                                 FROM run_gates g WHERE run_id = ? AND gate_no = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
        $s->execute([$runId, $gateNo]);
        return $s->fetch() ?: null;
    }

    public function gates(int $runId): array
    {
        $s = $this->db->prepare('SELECT gate_no, status FROM run_gates WHERE run_id = ? ORDER BY gate_no');
        $s->execute([$runId]);
        return $s->fetchAll();
    }

    public function pendingGateCount(int $runId): int
    {
        $s = $this->db->prepare("SELECT COUNT(*) FROM run_gates WHERE run_id = ? AND status = 'pending'");
        $s->execute([$runId]);
        return (int) $s->fetchColumn();
    }

    public function serveQuestion(int $runId, int $gateNo, int $questionId): void
    {
        $this->db->prepare('UPDATE run_gates SET question_id = ?, served_at = NOW(3) WHERE run_id = ? AND gate_no = ?')
                 ->execute([$questionId, $runId, $gateNo]);
    }

    public function updateGate(int $runId, int $gateNo, array $fields): void
    {
        $sets = implode(', ', array_map(fn ($k) => "`$k` = " . ($fields[$k] === 'NOW()' ? 'NOW()' : '?'), array_keys($fields)));
        $values = array_values(array_filter($fields, fn ($v) => $v !== 'NOW()'));
        $this->db->prepare("UPDATE run_gates SET $sets WHERE run_id = ? AND gate_no = ?")->execute([...$values, $runId, $gateNo]);
    }

    public function findAnswer(int $runId, int $gateNo, int $attemptNo): ?array
    {
        $s = $this->db->prepare('SELECT * FROM run_answers WHERE run_id = ? AND gate_no = ? AND attempt_no = ?');
        $s->execute([$runId, $gateNo, $attemptNo]);
        return $s->fetch() ?: null;
    }

    public function insertAnswer(int $runId, int $gateNo, int $attemptNo, int $questionId, int $chosen, bool $correct, int $coins, int $xp): void
    {
        $this->db->prepare('INSERT INTO run_answers (run_id, gate_no, attempt_no, question_id, chosen_idx, correct, coins_delta, xp_delta)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                 ->execute([$runId, $gateNo, $attemptNo, $questionId, $chosen, (int) $correct, $coins, $xp]);
    }
}
