<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LedgerRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

// Daily check-in and the spin wheel.
final class RewardService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private LedgerRepository $ledger = new LedgerRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
    ) {}

    public function checkin(int $userId): array
    {
        return Database::transaction(function () use ($userId) {
            $user = $this->users->find($userId, forUpdate: true);
            $db = Database::connection();
            [$today, $yesterday] = $db->query('SELECT CURDATE(), CURDATE() - INTERVAL 1 DAY')->fetch(\PDO::FETCH_NUM);

            if ($user['last_checkin'] === $today) {
                return ['claimed' => false, 'message' => 'Already checked in today', 'wallet' => $this->progression->wallet($userId)];
            }
            $streak = $user['last_checkin'] === $yesterday ? (int) $user['streak'] + 1 : 1;
            $coins = GameRules::CHECKIN_BASE + ($streak > 1 ? $streak * GameRules::CHECKIN_PER_STREAK_DAY : 0);

            // The date in the ref makes a second check-in on the same day a no-op.
            $this->progression->credit($userId, $coins, 0, 'checkin', $today);
            $this->users->setCheckin($userId, $today, $streak);
            $badges = $this->progression->awardBadges($userId);

            return [
                'claimed' => true, 'coins' => $coins, 'streak' => $streak, 'new_badges' => $badges,
                'message' => $streak > 1 ? "Streak continues: $streak days! +$coins coins" : "Check-in complete! +$coins coins",
                'wallet' => $this->progression->wallet($userId),
            ];
        });
    }

    // The old wheel cost 5 coins and paid ~26 coins on average with no limit —
    // an infinite coin machine. It is now limited to a few spins a day.
    public function spin(int $userId): array
    {
        return Database::transaction(function () use ($userId) {
            $this->users->find($userId, forUpdate: true); // serialise concurrent spins
            $used = $this->ledger->countToday($userId, 'spin');
            if ($used >= GameRules::SPINS_PER_DAY) {
                throw ApiError::conflict('SPIN_LIMIT', 'No spins left today — come back tomorrow!');
            }
            $spinRef = Database::connection()->query('SELECT CURDATE()')->fetchColumn() . ':' . ($used + 1);
            $this->progression->spend($userId, GameRules::SPIN_COST, 'spin', $spinRef);

            $index = random_int(0, count(GameRules::SPIN_SEGMENTS) - 1);
            $segment = GameRules::SPIN_SEGMENTS[$index];
            if (isset($segment['coins'])) {
                $this->progression->credit($userId, $segment['coins'], 0, 'spin_prize', $spinRef);
            } else {
                $this->shop->grant($userId, $segment['item'], 1);
            }
            return [
                'segment' => $index, 'prize' => $segment['label'],
                'spins_left' => GameRules::SPINS_PER_DAY - $used - 1,
                'wallet' => $this->progression->wallet($userId),
            ];
        });
    }
}
