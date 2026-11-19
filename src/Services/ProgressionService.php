<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LedgerRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;

// The only code allowed to change a player's coins or XP.
// Every change is a ledger row + an update of the cached totals on users,
// and callers run it inside a transaction.
final class ProgressionService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private LedgerRepository $ledger = new LedgerRepository(),
        private ShopRepository $shop = new ShopRepository(),
    ) {}

    // Award coins/XP once per (reason, ref). Returns false (and changes
    // nothing) if this exact award was already paid.
    public function credit(int $userId, int $coins, int $xp, string $reason, string $ref): bool
    {
        if (!$this->ledger->record($userId, $coins, $xp, $reason, $ref)) return false;
        $user = $this->users->find($userId, forUpdate: true);
        $newLevel = GameRules::levelForXp((int) $user['xp'] + $xp);
        $this->users->addBalance($userId, $coins, $xp, $newLevel);
        return true;
    }

    // Spend coins; throws if the player can't afford it. Never goes negative.
    public function spend(int $userId, int $coins, string $reason, string $ref): void
    {
        if (!$this->users->spendCoins($userId, $coins)) {
            throw ApiError::conflict('INSUFFICIENT_COINS', 'Not enough coins');
        }
        if (!$this->ledger->record($userId, -$coins, 0, $reason, $ref)) {
            throw ApiError::conflict('DUPLICATE', 'This purchase was already processed');
        }
    }

    // Take up to $coins (penalties never push a balance below zero).
    // Returns the amount actually taken.
    public function penalise(int $userId, int $coins, string $reason, string $ref): int
    {
        if ($coins <= 0) return 0;
        $user = $this->users->find($userId, forUpdate: true);
        $take = min($coins, (int) $user['coins']);
        if ($take > 0 && $this->ledger->record($userId, -$take, 0, $reason, $ref)) {
            $this->users->spendCoins($userId, $take);
            return $take;
        }
        return 0;
    }

    // Badge rules (previously checked in the browser). Returns new badges.
    public function awardBadges(int $userId): array
    {
        $u = $this->users->find($userId);
        $earned = [];
        $rules = [
            'Level 5'       => (int) $u['level'] >= 5,
            'Rich Explorer' => (int) $u['coins'] >= 500,
            '10 Day Streak' => (int) $u['streak'] >= 10,
        ];
        foreach ($rules as $badge => $ok) {
            if ($ok && $this->users->awardBadge($userId, $badge)) $earned[] = $badge;
        }
        return $earned;
    }

    // Everything the UI shows about a player's progress.
    public function wallet(int $userId): array
    {
        $u = $this->users->find($userId);
        $level = (int) $u['level'];
        $levelStart = GameRules::xpForLevel($level);
        return [
            'coins'         => (int) $u['coins'],
            'xp'            => (int) $u['xp'],
            'level'         => $level,
            'xp_into_level' => (int) $u['xp'] - $levelStart,
            'xp_for_next'   => GameRules::xpForLevel($level + 1) - $levelStart,
            'streak'        => (int) $u['streak'],
            'inventory'     => $this->shop->inventory($userId),
        ];
    }
}
