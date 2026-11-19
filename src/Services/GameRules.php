<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ApiError;

// Every number that affects coins, XP or difficulty lives here.
final class GameRules
{
    // gates:       question gates on the path to the exit (all must be cleared)
    // time:        seconds, or null for no timer
    // penalty:     coins lost per wrong answer
    // mult:        multiplier on the completion reward
    // min_seconds: fastest plausible completion; anything faster is rejected
    // fog:         fog-of-war radius in cells (display only), or null
    public const DIFFICULTIES = [
        'Easy'   => ['size' => 10, 'time' => null, 'gates' => 3, 'penalty' => 0,  'mult' => 1.0, 'min_seconds' => 6,  'fog' => null],
        'Medium' => ['size' => 15, 'time' => 150,  'gates' => 5, 'penalty' => 5,  'mult' => 1.5, 'min_seconds' => 10, 'fog' => null],
        'Hard'   => ['size' => 20, 'time' => 240,  'gates' => 7, 'penalty' => 10, 'mult' => 2.0, 'min_seconds' => 15, 'fog' => 3.5],
    ];

    public const MIXED_SUBJECT = 'Mixed Mode';

    public const GATE_COINS = 10;
    public const GATE_XP = 5;
    public const COMBO_STEP = 2;          // +2 coins per consecutive correct answer...
    public const COMBO_MAX_BONUS = 10;    // ...capped at +10
    public const COMPLETE_COINS = 50;
    public const COMPLETE_XP = 20;
    public const TIME_BONUS_DIVISOR = 5;  // +1 coin per 5 seconds left
    public const MIN_ANSWER_SECONDS = 1;  // answers faster than this after the question was served are rejected
    public const DEADLINE_GRACE_SECONDS = 3;
    public const FREEZE_SECONDS = 10;

    public const CHECKIN_BASE = 100;
    public const CHECKIN_PER_STREAK_DAY = 10;

    public const SPIN_COST = 5;
    public const SPINS_PER_DAY = 3;
    // Wheel segments in order (index = segment the wheel stops on).
    public const SPIN_SEGMENTS = [
        ['label' => '10 Coins',    'coins' => 10],
        ['label' => 'Time Freeze', 'item' => 'timeFreeze'],
        ['label' => '25 Coins',    'coins' => 25],
        ['label' => 'Hint Key',    'item' => 'hint'],
        ['label' => '50 Coins',    'coins' => 50],
        ['label' => 'Skip Gate',   'item' => 'skip'],
    ];

    public const POWERUPS = ['timeFreeze', 'hint', 'skip', 'scoreDoubler'];
    public const STARTER_POWERUPS = 3;    // of each, for new players
    public const STARTER_COINS = 100;

    public static function difficulty(string $name): array
    {
        $cfg = self::DIFFICULTIES[$name] ?? null;
        if ($cfg === null) {
            throw ApiError::badRequest('INVALID_DIFFICULTY', 'Difficulty must be Easy, Medium or Hard');
        }
        return $cfg;
    }

    // Coins for a correct answer. Full reward on the first try, half on the
    // second, nothing after that — guessing through all options doesn't pay.
    public static function gateCoins(int $attemptNo, int $combo, bool $doubled): int
    {
        if ($attemptNo >= 3) return 0;
        $coins = self::GATE_COINS + min(($combo - 1) * self::COMBO_STEP, self::COMBO_MAX_BONUS);
        if ($attemptNo === 2) $coins = intdiv($coins, 2);
        return $doubled ? $coins * 2 : $coins;
    }

    public static function gateXp(int $attemptNo, bool $doubled): int
    {
        if ($attemptNo >= 3) return 0;
        return $doubled ? self::GATE_XP * 2 : self::GATE_XP;
    }

    // XP is lifetime total. Reaching level L needs 100 * (1 + 2 + ... + (L-1))
    // = 50·L·(L-1) XP — the same curve as the old "level × 100 per level" rule.
    public static function levelForXp(int $xp): int
    {
        return (int) floor((1 + sqrt(1 + 8 * max(0, $xp) / 100)) / 2);
    }

    public static function xpForLevel(int $level): int
    {
        return 50 * $level * ($level - 1);
    }
}
