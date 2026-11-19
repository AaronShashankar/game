<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class RunRepository
{
    private PDO $db;

    public function __construct() { $this->db = Database::connection(); }

    public function create(int $userId, string $difficulty, string $subject, int $seed, int $gateCount, ?int $timeLimit): int
    {
        $this->db->prepare('INSERT INTO runs (user_id, difficulty, subject, seed, gate_count, started_at, deadline_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), IF(? IS NULL, NULL, NOW() + INTERVAL ? SECOND))')
                 ->execute([$userId, $difficulty, $subject, $seed, $gateCount, $timeLimit, $timeLimit ?? 0]);
        $runId = (int) $this->db->lastInsertId();

        $ins = $this->db->prepare('INSERT INTO run_gates (run_id, gate_no) VALUES (?, ?)');
        for ($n = 1; $n <= $gateCount; $n++) $ins->execute([$runId, $n]);
        return $runId;
    }

    public function abandonActive(int $userId): void
    {
        $this->db->prepare("UPDATE runs SET status = 'abandoned' WHERE user_id = ? AND status = 'active'")->execute([$userId]);
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
