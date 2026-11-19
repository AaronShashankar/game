<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;
use App\Repositories\RunRepository;
use App\Repositories\SecurityLogRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

// One maze attempt. The browser draws the maze and reports events; this class
// decides whether each event is valid and what it pays.
final class RunService
{
    // Error codes worth recording: a normal client never produces these.
    private const SUSPICIOUS = ['TOO_FAST', 'GATES_PENDING', 'WRONG_ATTEMPT', 'NO_QUESTION_SERVED', 'INVALID_GATE', 'INVALID_OPTION'];

    public function __construct(
        private RunRepository $runs = new RunRepository(),
        private QuestionRepository $questions = new QuestionRepository(),
        private UserRepository $users = new UserRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private SecurityLogRepository $log = new SecurityLogRepository(),
    ) {}

    public function start(int $userId, string $difficulty, string $subject, ?int $replayOf): array
    {
        $cfg = GameRules::difficulty($difficulty);
        if ($subject !== GameRules::MIXED_SUBJECT && !in_array($subject, $this->questions->subjects(), true)) {
            throw ApiError::badRequest('INVALID_SUBJECT', 'Unknown subject');
        }

        return Database::transaction(function () use ($userId, $difficulty, $subject, $replayOf, $cfg) {
            $seed = random_int(1, 2_147_483_647);
            if ($replayOf !== null) {
                // "Restart" replays the same maze layout as a fresh run.
                $prev = $this->runs->find($replayOf, $userId);
                if ($prev === null) throw ApiError::notFound('Run not found');
                $seed = (int) $prev['seed'];
            }
            $this->runs->abandonActive($userId);
            $runId = $this->runs->create($userId, $difficulty, $subject, $seed, $cfg['gates'], $cfg['time']);
            $this->users->incrementStat($userId, 'total_games');
            return $this->state($this->runs->find($runId, $userId));
        });
    }

    public function question(int $userId, int $runId, int $gateNo): array
    {
        return $this->guarded($userId, "question run $runId gate $gateNo", function () use ($userId, $runId, $gateNo) {
            $run = $this->activeRun($userId, $runId, lock: true);
            $gate = $this->pendingGate($run, $gateNo, lock: true);

            // Asking again for the same gate returns the same question, so
            // re-requesting can't be used to shop for an easier one.
            if ($gate['question_id'] !== null) {
                $q = $this->questions->find((int) $gate['question_id']);
            } else {
                $q = $this->questions->pickForRun($run['subject'], $runId);
                if ($q === null) throw ApiError::conflict('NO_QUESTIONS', 'No questions available for this subject');
                $this->runs->serveQuestion($runId, $gateNo, (int) $q['id']);
            }
            return ['gate_no' => $gateNo, 'attempt_no' => (int) $gate['attempts'] + 1] + QuestionRepository::publicView($q);
        });
    }

    public function answer(int $userId, int $runId, int $gateNo, int $attemptNo, int $chosenIdx): array
    {
        return $this->guarded($userId, "answer run $runId gate $gateNo", function () use ($userId, $runId, $gateNo, $attemptNo, $chosenIdx) {
            $run = $this->activeRun($userId, $runId, lock: true);

            // Idempotent retry: the same attempt sent twice returns the stored outcome.
            $previous = $this->runs->findAnswer($runId, $gateNo, $attemptNo);
            if ($previous !== null) {
                return $this->answerResult((bool) $previous['correct'], (int) $previous['coins_delta'], (int) $previous['xp_delta'], $userId, $runId, $gateNo, [], true);
            }

            $gate = $this->pendingGate($run, $gateNo, lock: true);
            $this->assertNotExpired($run, $userId);
            if ($gate['question_id'] === null) {
                throw ApiError::conflict('NO_QUESTION_SERVED', 'Request the question before answering');
            }
            if ($attemptNo !== (int) $gate['attempts'] + 1) {
                throw ApiError::conflict('WRONG_ATTEMPT', 'Unexpected attempt number');
            }
            if ((float) $gate['seconds_since_served'] < GameRules::MIN_ANSWER_SECONDS) {
                throw ApiError::conflict('TOO_FAST', 'Answer submitted too quickly');
            }

            $q = $this->questions->find((int) $gate['question_id']);
            if ($chosenIdx < 0 || $chosenIdx >= count($q['options'])) {
                throw ApiError::badRequest('INVALID_OPTION', 'Option out of range');
            }
            $correct = $chosenIdx === (int) $q['answer_idx'];
            $cfg = GameRules::difficulty($run['difficulty']);
            $ref = "run:$runId:gate:$gateNo";
            $coins = 0; $xp = 0;

            if ($correct) {
                $combo = (int) $run['combo'] + 1;
                $doubled = (bool) $run['doubler_pending'];
                $coins = GameRules::gateCoins($attemptNo, $combo, $doubled);
                $xp = GameRules::gateXp($attemptNo, $doubled);
                $this->progression->credit($userId, $coins, $xp, 'gate', $ref);
                $this->runs->updateGate($runId, $gateNo, ['status' => 'cleared', 'attempts' => $attemptNo, 'cleared_at' => 'NOW()']);
                $this->runs->update($runId, [
                    'combo' => $combo, 'doubler_pending' => 0,
                    'coins_earned' => (int) $run['coins_earned'] + $coins, 'xp_earned' => (int) $run['xp_earned'] + $xp,
                ]);
                $this->users->incrementStat($userId, 'correct_answers');
            } else {
                $coins = -$this->progression->penalise($userId, $cfg['penalty'], 'wrong_answer', "$ref:try:$attemptNo");
                // A wrong answer gets a new question next time.
                $this->runs->updateGate($runId, $gateNo, ['attempts' => $attemptNo, 'question_id' => null, 'served_at' => null]);
                $this->runs->update($runId, ['combo' => 0, 'coins_earned' => (int) $run['coins_earned'] + $coins]);
                $this->users->incrementStat($userId, 'wrong_answers');
            }
            $this->runs->insertAnswer($runId, $gateNo, $attemptNo, (int) $q['id'], $chosenIdx, $correct, $coins, $xp);
            $badges = $this->progression->awardBadges($userId);

            return $this->answerResult($correct, $coins, $xp, $userId, $runId, $gateNo, $badges, false);
        });
    }

    public function usePowerup(int $userId, int $runId, string $type, ?int $gateNo): array
    {
        if (!in_array($type, GameRules::POWERUPS, true)) {
            throw ApiError::badRequest('INVALID_POWERUP', 'Unknown power-up');
        }
        return $this->guarded($userId, "powerup $type run $runId", function () use ($userId, $runId, $type, $gateNo) {
            $run = $this->activeRun($userId, $runId, lock: true);
            $effect = [];

            // Validate before consuming so a rejected use doesn't cost anything.
            if ($type === 'timeFreeze') {
                if ($run['deadline_at'] === null) throw ApiError::conflict('NO_TIMER', 'This difficulty has no timer');
                if ($run['frozen']) throw ApiError::conflict('ALREADY_FROZEN', 'The timer is already frozen');
                $this->assertNotExpired($run, $userId);
            } elseif ($type === 'skip') {
                if ($gateNo === null) throw ApiError::badRequest('INVALID_INPUT', "'gate_no' is required for skip");
                $this->pendingGate($run, $gateNo, lock: true);
            } elseif ($type === 'scoreDoubler' && $run['doubler_pending']) {
                throw ApiError::conflict('ALREADY_ACTIVE', 'Score Doubler is already active');
            }

            if (!$this->shop->consume($userId, $type)) {
                throw ApiError::conflict('NO_POWERUP', 'You have none of that power-up left');
            }
            $this->users->incrementStat($userId, 'powerups_used');

            switch ($type) {
                case 'timeFreeze':
                    $this->runs->extendDeadline($runId, GameRules::FREEZE_SECONDS);
                    $effect = ['frozen_seconds' => GameRules::FREEZE_SECONDS];
                    break;
                case 'skip':
                    $this->runs->updateGate($runId, $gateNo, ['status' => 'skipped', 'cleared_at' => 'NOW()']);
                    $effect = ['skipped_gate' => $gateNo];
                    break;
                case 'scoreDoubler':
                    $this->runs->update($runId, ['doubler_pending' => 1]);
                    break;
                case 'hint':
                    // Purely visual — the browser highlights the nearest gate.
                    break;
            }
            return ['effect' => $effect, 'run' => $this->state($this->runs->find($runId, $userId)), 'wallet' => $this->progression->wallet($userId)];
        });
    }

    public function complete(int $userId, int $runId): array
    {
        return $this->guarded($userId, "complete run $runId", function () use ($userId, $runId) {
            $run = $this->activeRun($userId, $runId, lock: true);
            $cfg = GameRules::difficulty($run['difficulty']);

            if ($this->runs->pendingGateCount($runId) > 0) {
                throw ApiError::conflict('GATES_PENDING', 'Every gate must be cleared first');
            }
            if ((int) $run['elapsed'] < $cfg['min_seconds']) {
                throw ApiError::conflict('TOO_FAST', 'That was impossibly fast');
            }
            $this->assertNotExpired($run, $userId);

            $secondsLeft = $run['deadline_at'] === null ? 0 : max(0, (int) $run['seconds_left']);
            $timeBonus = intdiv($secondsLeft, GameRules::TIME_BONUS_DIVISOR);
            $coins = (int) round(GameRules::COMPLETE_COINS * $cfg['mult']) + $timeBonus;
            $xp = (int) round(GameRules::COMPLETE_XP * $cfg['mult']);

            $levelBefore = (int) $this->users->find($userId)['level'];
            $this->progression->credit($userId, $coins, $xp, 'complete', "run:$runId");
            $this->runs->complete($runId, $coins, $xp);
            $badges = $this->progression->awardBadges($userId);
            $wallet = $this->progression->wallet($userId);

            return [
                'coins' => $coins, 'xp' => $xp, 'time_bonus' => $timeBonus,
                'level_up' => $wallet['level'] > $levelBefore ? $wallet['level'] : null,
                'new_badges' => $badges, 'wallet' => $wallet,
            ];
        });
    }

    // The timer ran out: the browser sends the player back to the start and
    // the server gives a fresh deadline. Cleared gates stay cleared.
    public function timeout(int $userId, int $runId): array
    {
        return Database::transaction(function () use ($userId, $runId) {
            $run = $this->activeRun($userId, $runId, lock: true);
            $cfg = GameRules::difficulty($run['difficulty']);
            if ($run['deadline_at'] === null) throw ApiError::conflict('NO_TIMER', 'This difficulty has no timer');
            if ((int) $run['seconds_left'] > 1) throw ApiError::conflict('NOT_EXPIRED', 'Time has not run out yet');
            $this->runs->resetDeadline($runId, $cfg['time']);
            return ['run' => $this->state($this->runs->find($runId, $userId))];
        });
    }

    // ---- helpers ----------------------------------------------------------

    private function activeRun(int $userId, int $runId, bool $lock = false): array
    {
        $run = $this->runs->find($runId, $userId, $lock);
        if ($run === null) throw ApiError::notFound('Run not found');
        if ($run['status'] !== 'active') throw ApiError::conflict('RUN_NOT_ACTIVE', 'This run is already ' . $run['status']);
        return $run;
    }

    private function pendingGate(array $run, int $gateNo, bool $lock = false): array
    {
        if ($gateNo < 1 || $gateNo > (int) $run['gate_count']) {
            throw ApiError::badRequest('INVALID_GATE', 'No such gate');
        }
        $gate = $this->runs->gate((int) $run['id'], $gateNo, $lock);
        if ($gate['status'] !== 'pending') throw ApiError::conflict('GATE_NOT_PENDING', 'That gate is already open');
        return $gate;
    }

    private function assertNotExpired(array $run, int $userId): void
    {
        if ($run['deadline_at'] !== null && (int) $run['seconds_left'] < -GameRules::DEADLINE_GRACE_SECONDS) {
            throw ApiError::conflict('EXPIRED', "Time's up");
        }
    }

    private function answerResult(bool $correct, int $coins, int $xp, int $userId, int $runId, int $gateNo, array $badges, bool $replayed): array
    {
        $run = $this->runs->find($runId, $userId);
        return [
            'correct' => $correct, 'coins' => $coins, 'xp' => $xp, 'combo' => (int) $run['combo'],
            'gate_no' => $gateNo, 'new_badges' => $badges, 'replayed' => $replayed,
            'run' => $this->state($run), 'wallet' => $this->progression->wallet($userId),
        ];
    }

    // Run $fn in a transaction. Rejections that suggest tampering are written
    // to the security log *after* the rollback, so the log entry survives.
    private function guarded(int $userId, string $what, callable $fn): array
    {
        try {
            return Database::transaction($fn);
        } catch (ApiError $e) {
            if (in_array($e->errorCode, self::SUSPICIOUS, true)) {
                $this->log->log('run_rejected:' . $e->errorCode, "user:$userId", $_SERVER['REMOTE_ADDR'] ?? null, $what);
            }
            throw $e;
        }
    }

    // Run state as the browser needs it.
    public function state(array $run): array
    {
        return [
            'run_id'       => (int) $run['id'],
            'difficulty'   => $run['difficulty'],
            'subject'      => $run['subject'],
            'seed'         => (int) $run['seed'],
            'gate_count'   => (int) $run['gate_count'],
            'status'       => $run['status'],
            'seconds_left' => $run['deadline_at'] === null ? null : max(0, (int) $run['seconds_left']),
            'frozen'       => (bool) $run['frozen'],
            'freeze_left'  => (int) $run['freeze_left'],   // seconds_left includes this; the clock is paused for it
            'combo'        => (int) $run['combo'],
            'doubler'      => (bool) $run['doubler_pending'],
            'gates'        => $this->runs->gates((int) $run['id']),
        ];
    }
}
