<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LedgerRepository;
use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

// Daily check-in (with streak freezes) and the spin wheel.
final class RewardService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private LedgerRepository $ledger = new LedgerRepository(),
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private AchievementService $achievements = new AchievementService(),
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

            // Missed days are covered by streak freezes, one per day, if the
            // player holds enough of them; otherwise the streak starts over.
            $freezesUsed = 0;
            if ($user['last_checkin'] === $yesterday) {
                $streak = (int) $user['streak'] + 1;
            } elseif ($user['last_checkin'] !== null && (int) $user['streak'] > 0) {
                $missed = (int) $db->query('SELECT DATEDIFF(CURDATE(), ' . $db->quote($user['last_checkin']) . ') - 1')->fetchColumn();
                $held = $this->shop->inventory($userId)['streakFreeze'] ?? 0;
                if ($missed <= $held) {
                    for ($i = 0; $i < $missed; $i++) $this->shop->consume($userId, 'streakFreeze');
                    $freezesUsed = $missed;
                    $streak = (int) $user['streak'] + 1;
                } else {
                    $streak = 1;
                }
            } else {
                $streak = 1;
            }
            $coins = GameRules::CHECKIN_BASE + ($streak > 1 ? $streak * GameRules::CHECKIN_PER_STREAK_DAY : 0);

            // The date in the ref makes a second check-in on the same day a no-op.
            $this->progression->credit($userId, $coins, 0, 'checkin', $today);
            $this->users->setCheckin($userId, $today, $streak);
            $unlocked = $this->achievements->check($userId);

            $message = $streak > 1 ? "Streak continues: $streak days! +$coins coins" : "Check-in complete! +$coins coins";
            if ($freezesUsed > 0) {
                $message = ($freezesUsed === 1 ? 'A streak freeze covered the day you missed. ' : "$freezesUsed streak freezes covered the days you missed. ") . $message;
            }
            return [
                'claimed' => true, 'coins' => $coins, 'streak' => $streak, 'freezes_used' => $freezesUsed,
                'new_achievements' => $unlocked, 'message' => $message,
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
