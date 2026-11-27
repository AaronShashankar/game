<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;
use App\Repositories\RunRepository;
use App\Repositories\SecurityLogRepository;
use App\Repositories\ShopRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

// One maze attempt. The browser draws the maze and reports events; this class
// decides whether each event is valid and what it pays.
//
// Modes:
//   normal     a random maze; questions prefer the maze's difficulty and mix in
//              due review questions
//   daily      today's shared maze: same seed and question order for everyone,
//              one ranked attempt per player (resumable while it's active)
//   challenge  a friend's finished maze: same seed, and the same question at
//              each gate and attempt that they were asked
final class RunService
{
    // Error codes worth recording: a normal client never produces these.
    private const SUSPICIOUS = ['TOO_FAST', 'GATES_PENDING', 'WRONG_ATTEMPT', 'NO_QUESTION_SERVED', 'INVALID_GATE', 'INVALID_OPTION', 'INVALID_PICKUP'];

    public function __construct(
        private RunRepository $runs = new RunRepository(),
        private QuestionRepository $questions = new QuestionRepository(),
        private UserRepository $users = new UserRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private SecurityLogRepository $log = new SecurityLogRepository(),
        private LearningService $learning = new LearningService(),
        private AchievementService $achievements = new AchievementService(),
        private DailyService $daily = new DailyService(),
        private ChallengeService $challenges = new ChallengeService(),
        private SubjectRepository $subjects = new SubjectRepository(),
    ) {}

    // ---- Starting a run ---------------------------------------------------------

    public function start(int $userId, string $difficulty, string $subject, ?int $replayOf): array
    {
        $cfg = GameRules::difficulty($difficulty);
        if ($subject !== GameRules::MIXED_SUBJECT && !in_array($subject, $this->subjects->playableNames(), true)) {
            throw ApiError::badRequest('INVALID_SUBJECT', 'Unknown subject');
        }

        return Database::transaction(function () use ($userId, $difficulty, $subject, $replayOf, $cfg) {
            $seed = random_int(1, 2_147_483_647);
            if ($replayOf !== null) {
                // "Restart" replays the same maze layout as a fresh run. The daily
                // maze can't be replayed: that would allow unlimited practice.
                $prev = $this->runs->find($replayOf, $userId);
                if ($prev === null) throw ApiError::notFound('Run not found');
                if ($prev['mode'] === 'daily') throw ApiError::conflict('DAILY_DONE', 'The daily maze can only be played once');
                $seed = (int) $prev['seed'];
            }
            return $this->createRun($userId, $difficulty, $subject, $seed, $cfg['gates'], ['mode' => 'normal']);
        });
    }

    public function startDaily(int $userId): array
    {
        return Database::transaction(function () use ($userId) {
            $this->users->find($userId, forUpdate: true);   // one start at a time per player
            $today = $this->daily->today();
            $existing = $this->runs->findDaily($userId, $today['day']);
            if ($existing !== null) {
                if ($existing['status'] !== 'active') {
                    throw ApiError::conflict('DAILY_DONE', "You've already played today's maze. A new one opens tomorrow.");
                }
                return ['run' => $this->state($existing), 'resumed' => true];
            }
            $cfg = GameRules::difficulty(GameRules::DAILY_DIFFICULTY);
            return $this->createRun($userId, GameRules::DAILY_DIFFICULTY, GameRules::MIXED_SUBJECT, $today['seed'], $cfg['gates'],
                                    ['mode' => 'daily', 'daily_date' => $today['day']]);
        });
    }

    public function startChallenge(int $userId, string $code): array
    {
        $code = strtoupper(trim($code));
        $source = ChallengeService::isValidCode($code) ? $this->runs->findByShareCode($code) : null;
        if ($source === null) throw ApiError::notFound('That challenge link is not valid');
        if ((int) $source['user_id'] === $userId) {
            throw ApiError::conflict('OWN_CHALLENGE', "That's your own maze. Send the link to a friend instead.");
        }
        return Database::transaction(fn () => $this->createRun(
            $userId, $source['difficulty'], $source['subject'], (int) $source['seed'], (int) $source['gate_count'],
            ['mode' => 'challenge', 'challenge_of' => (int) $source['id']],
        ));
    }

    private function createRun(int $userId, string $difficulty, string $subject, int $seed, int $gates, array $extra): array
    {
        $cfg = GameRules::difficulty($difficulty);
        $this->runs->abandonActive($userId);
        $runId = $this->runs->create($userId, $difficulty, $subject, $seed, $gates, $cfg['time'], $extra + ['pickup_count' => $cfg['pickups']]);
        $this->users->incrementStat($userId, 'total_games');
        return ['run' => $this->state($this->runs->find($runId, $userId)), 'resumed' => false];
    }

    // ---- Questions and answers ----------------------------------------------------

    public function question(int $userId, int $runId, int $gateNo): array
    {
        return $this->guarded($userId, "question run $runId gate $gateNo", function () use ($userId, $runId, $gateNo) {
            $run = $this->activeRun($userId, $runId, lock: true);
            $gate = $this->pendingGate($run, $gateNo, lock: true);
            $isReview = false;

            // Asking again for the same gate returns the same question, so
            // re-requesting can't be used to shop for an easier one.
            // (If an admin deleted it meanwhile, a new one is picked below.)
            $q = $gate['question_id'] !== null ? $this->questions->find((int) $gate['question_id']) : null;
            if ($q === null) {
                [$q, $isReview] = $this->pickQuestion($run, $userId, $gateNo, (int) $gate['attempts'] + 1);
                if ($q === null) throw ApiError::conflict('NO_QUESTIONS', 'No questions available for this subject');
                $this->runs->serveQuestion($runId, $gateNo, (int) $q['id']);
            }
            return ['gate_no' => $gateNo, 'attempt_no' => (int) $gate['attempts'] + 1, 'is_review' => $isReview]
                 + QuestionRepository::publicView($q);
        });
    }

    /** @return array{0: ?array, 1: bool} the question and whether it's a review */
    private function pickQuestion(array $run, int $userId, int $gateNo, int $attemptNo): array
    {
        $runId = (int) $run['id'];
        if ($run['mode'] === 'challenge') {
            $sourceId = $this->runs->sourceQuestionId((int) $run['challenge_of'], $gateNo, $attemptNo);
            $q = $sourceId !== null ? $this->questions->find($sourceId) : null;
            if ($q !== null && $q['active']) return [$q, false];
            // The friend never needed this attempt (or the question was archived): pick normally.
        }
        if ($run['mode'] === 'daily') {
            return [$this->questions->pickSeeded($run['subject'], $run['difficulty'], $runId, (int) $run['seed']), false];
        }
        if ($run['mode'] === 'normal' && (int) $run['reviews_served'] < GameRules::MAX_REVIEWS_PER_RUN) {
            $q = $this->questions->pickDueReview($userId, $run['subject'], $runId);
            if ($q !== null) {
                $this->runs->noteReviewServed($runId);
                return [$q, true];
            }
        }
        return [$this->questions->pickRandom($run['subject'], $run['difficulty'], $runId), false];
    }

    public function answer(int $userId, int $runId, int $gateNo, int $attemptNo, int $chosenIdx): array
    {
        return $this->guarded($userId, "answer run $runId gate $gateNo", function () use ($userId, $runId, $gateNo, $attemptNo, $chosenIdx) {
            $run = $this->activeRun($userId, $runId, lock: true);

            // Idempotent retry: the same attempt sent twice returns the stored outcome.
            $previous = $this->runs->findAnswer($runId, $gateNo, $attemptNo);
            if ($previous !== null) {
                $q = $this->questions->find((int) $previous['question_id']);
                return $this->answerResult($q, (bool) $previous['correct'], (int) $previous['coins_delta'], (int) $previous['xp_delta'],
                                           $userId, $runId, $gateNo, [], false, true);
            }

            $gate = $this->pendingGate($run, $gateNo, lock: true);
            $this->assertNotExpired($run);
            if ($gate['question_id'] === null) {
                throw ApiError::conflict('NO_QUESTION_SERVED', 'Request the question before answering');
            }
            if ($attemptNo !== (int) $gate['attempts'] + 1) {
                throw ApiError::conflict('WRONG_ATTEMPT', 'Unexpected attempt number');
            }
            if ((float) $gate['seconds_since_served'] < GameRules::MIN_ANSWER_SECONDS) {
                throw ApiError::conflict('TOO_FAST', 'Answer submitted too quickly');
            }

            $q = $this->questions->find((int) $gate['question_id'])
                ?? throw ApiError::conflict('NO_QUESTION_SERVED', 'This question was just removed. Walk into the gate again for a new one');
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
                    'combo' => $combo, 'best_combo' => max($combo, (int) $run['best_combo']), 'doubler_pending' => 0,
                    'coins_earned' => (int) $run['coins_earned'] + $coins, 'xp_earned' => (int) $run['xp_earned'] + $xp,
                ]);
                $this->users->incrementStat($userId, 'correct_answers');
            } else {
                $coins = -$this->progression->penalise($userId, $cfg['penalty'], 'wrong_answer', "$ref:try:$attemptNo");
                // A wrong answer gets a new question next time, which is what makes
                // it safe to show the correct answer straight away.
                $this->runs->updateGate($runId, $gateNo, ['attempts' => $attemptNo, 'question_id' => null, 'served_at' => null]);
                $this->runs->update($runId, ['combo' => 0, 'coins_earned' => (int) $run['coins_earned'] + $coins]);
                $this->users->incrementStat($userId, 'wrong_answers');
            }
            $this->runs->insertAnswer($runId, $gateNo, $attemptNo, (int) $q['id'], $chosenIdx, $correct, $coins, $xp);
            $wasReview = $this->learning->recordAnswer($userId, (int) $q['id'], $correct);
            $unlocked = $this->achievements->check($userId, $this->runs->find($runId, $userId));

            return $this->answerResult($q, $correct, $coins, $xp, $userId, $runId, $gateNo, $unlocked, $wasReview, false);
        });
    }

    // ---- Power-ups and pickups ------------------------------------------------------

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
                $this->assertNotExpired($run);
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
                    // Purely visual: the browser highlights the nearest gate.
                    break;
            }
            return ['effect' => $effect, 'run' => $this->state($this->runs->find($runId, $userId)), 'wallet' => $this->progression->wallet($userId)];
        });
    }

    // A coin in a dead end. The browser places pickups from the seed, the
    // same way it places gates. Collecting one only marks it; coins for
    // collected pickups are paid when the maze is finished.
    public function pickup(int $userId, int $runId, int $pickupNo): array
    {
        return $this->guarded($userId, "pickup $pickupNo run $runId", function () use ($userId, $runId, $pickupNo) {
            $run = $this->activeRun($userId, $runId, lock: true);
            if ($pickupNo < 1 || $pickupNo > (int) $run['pickup_count']) {
                throw ApiError::badRequest('INVALID_PICKUP', 'No such pickup');
            }
            $new = $this->runs->collectPickup($runId, $pickupNo);
            return ['collected' => $new, 'pickup_no' => $pickupNo, 'coins_on_finish' => GameRules::PICKUP_COINS,
                    'run' => $this->state($this->runs->find($runId, $userId))];
        });
    }

    // ---- Finishing --------------------------------------------------------------------

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
            $this->assertNotExpired($run);

            $secondsLeft = $run['deadline_at'] === null ? 0 : max(0, (int) $run['seconds_left']);
            $timeBonus = intdiv($secondsLeft, GameRules::TIME_BONUS_DIVISOR);
            $coins = (int) round(GameRules::COMPLETE_COINS * $cfg['mult']) + $timeBonus;
            $xp = (int) round(GameRules::COMPLETE_XP * $cfg['mult']);
            $pickups = self::bitCount((int) $run['pickups_mask']);
            $pickupCoins = GameRules::pickupCoins($pickups);

            $levelBefore = (int) $this->users->find($userId)['level'];
            $this->progression->credit($userId, $coins, $xp, 'complete', "run:$runId");
            if ($pickupCoins > 0) $this->progression->credit($userId, $pickupCoins, 0, 'pickups', "run:$runId");

            $dailyBonus = null;
            if ($run['mode'] === 'daily') {
                $this->progression->credit($userId, GameRules::DAILY_BONUS_COINS, GameRules::DAILY_BONUS_XP, 'daily', (string) $run['daily_date']);
                $dailyBonus = ['coins' => GameRules::DAILY_BONUS_COINS, 'xp' => GameRules::DAILY_BONUS_XP];
            }
            $this->runs->complete($runId, $coins + $pickupCoins + ($dailyBonus['coins'] ?? 0), $xp + ($dailyBonus['xp'] ?? 0));
            $run = $this->runs->find($runId, $userId);

            $summary = $this->summary($run);
            // A daily maze isn't shareable: the link would reveal today's maze and questions.
            $shareCode = $run['mode'] === 'daily' ? null : $this->challenges->assignCode($runId);

            $challenge = null;
            if ($run['mode'] === 'challenge') {
                $source = $this->runs->findById((int) $run['challenge_of']);
                $challenge = [
                    'opponent'         => $source['username'],
                    'opponent_seconds' => (int) $source['duration_seconds'],
                    'your_seconds'     => (int) $run['duration_seconds'],
                    'won'              => (int) $run['duration_seconds'] < (int) $source['duration_seconds'],
                ];
            }
            $daily = $run['mode'] === 'daily' ? [
                'rank'      => $this->runs->dailyRank((string) $run['daily_date'], $runId),
                'finishers' => $this->runs->dailyFinishers((string) $run['daily_date']),
            ] : null;

            $unlocked = $this->achievements->check($userId, $run, [
                'wrong' => $summary['wrong'], 'skipped' => $summary['skipped'], 'challenge_won' => $challenge['won'] ?? false,
            ]);
            $wallet = $this->progression->wallet($userId);

            return [
                'coins' => $coins, 'xp' => $xp, 'time_bonus' => $timeBonus,
                'pickup_coins' => $pickupCoins, 'daily_bonus' => $dailyBonus,
                'level_up' => $wallet['level'] > $levelBefore ? $wallet['level'] : null,
                'new_achievements' => $unlocked, 'wallet' => $wallet,
                'summary' => $summary, 'share_code' => $shareCode, 'challenge' => $challenge, 'daily' => $daily,
            ];
        });
    }

    // End-of-run recap: how it went, and every question missed with the right
    // answer and why.
    private function summary(array $run): array
    {
        $answers = $this->runs->answersWithQuestions((int) $run['id']);
        $correct = count(array_filter($answers, fn ($a) => (int) $a['correct'] === 1));
        $missed = [];
        foreach ($answers as $a) {
            if ((int) $a['correct'] === 1) continue;
            $options = json_decode($a['options'], true);
            $missed[] = [
                'gate_no'        => (int) $a['gate_no'],
                'prompt'         => $a['prompt'],
                'your_answer'    => $options[(int) $a['chosen_idx']] ?? null,
                'correct_answer' => $options[(int) $a['answer_idx']] ?? null,
                'explanation'    => $a['explanation'],
            ];
        }
        $skipped = count(array_filter($this->runs->gates((int) $run['id']), fn ($g) => $g['status'] === 'skipped'));
        return [
            'duration_seconds' => (int) $run['duration_seconds'],
            'answered'         => count($answers),
            'correct'          => $correct,
            'wrong'            => count($answers) - $correct,
            'accuracy'         => $answers ? (int) round(100 * $correct / count($answers)) : null,
            'best_combo'       => (int) $run['best_combo'],
            'skipped'          => $skipped,
            'pickups'          => self::bitCount((int) $run['pickups_mask']),
            'pickup_count'     => (int) $run['pickup_count'],
            'missed'           => $missed,
        ];
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

    private function assertNotExpired(array $run): void
    {
        if ($run['deadline_at'] !== null && (int) $run['seconds_left'] < -GameRules::DEADLINE_GRACE_SECONDS) {
            throw ApiError::conflict('EXPIRED', "Time's up");
        }
    }

    // The outcome of an answer. The correct option and explanation are always
    // included: a wrong answer gets a different question on the next attempt.
    private function answerResult(array $q, bool $correct, int $coins, int $xp, int $userId, int $runId, int $gateNo,
                                  array $unlocked, bool $wasReview, bool $replayed): array
    {
        $run = $this->runs->find($runId, $userId);
        return [
            'correct' => $correct, 'coins' => $coins, 'xp' => $xp, 'combo' => (int) $run['combo'],
            'correct_idx' => (int) $q['answer_idx'], 'explanation' => $q['explanation'] ?? null, 'was_review' => $wasReview,
            'gate_no' => $gateNo, 'new_achievements' => $unlocked, 'replayed' => $replayed,
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

    private static function bitCount(int $mask): int
    {
        return substr_count(decbin($mask), '1');
    }

    // Run state as the browser needs it.
    public function state(array $run): array
    {
        $mask = (int) $run['pickups_mask'];
        $state = [
            'run_id'       => (int) $run['id'],
            'mode'         => $run['mode'],
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
            'pickup_count' => (int) $run['pickup_count'],
            'pickups'      => array_values(array_filter(range(1, max(1, (int) $run['pickup_count'])), fn ($n) => ($mask >> ($n - 1)) & 1)),
            'gates'        => $this->runs->gates((int) $run['id']),
        ];
        if ($run['mode'] === 'challenge') {
            $source = $this->runs->findById((int) $run['challenge_of']);
            $state['opponent'] = ['username' => $source['username'], 'duration_seconds' => (int) $source['duration_seconds']];
        }
        return $state;
    }
}
