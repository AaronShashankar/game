<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LedgerRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;

final class ProfileService
{
    private const MAX_AVATAR_BYTES = 300_000; // uploaded avatars are stored as data URLs

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private LedgerRepository $ledger = new LedgerRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private AchievementService $achievements = new AchievementService(),
        private LearningService $learning = new LearningService(),
        private WeeklyRewardService $weekly = new WeeklyRewardService(),
    ) {}

    public function me(int $userId): array
    {
        $u = $this->users->find($userId);
        if ($u === null) throw ApiError::unauthorized();
        $this->weekly->settleLastWeek();   // pays last week's prizes on the first visit after the week ends
        $u = $this->users->find($userId);

        // XP per day for the last 7 days (dashboard chart), oldest first.
        // Day keys come from the database clock so they match the ledger's dates.
        $byDay = $this->ledger->xpByDay($userId);
        $today = new \DateTimeImmutable((string) \App\Support\Database::connection()->query('SELECT CURDATE()')->fetchColumn());
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->modify("-$i day")->format('Y-m-d');
            $chart[] = ['day' => $day, 'xp' => (int) ($byDay[$day] ?? 0)];
        }
        $wallet = $this->progression->wallet($userId);

        return [
            'user' => [
                'id'              => (int) $u['id'],
                'username'        => $u['username'],
                'email'           => $u['email'],
                'avatar'          => $u['avatar'] ?: null,
                'last_checkin'    => $u['last_checkin'],
                'total_games'     => (int) $u['total_games'],
                'correct_answers' => (int) $u['correct_answers'],
                'wrong_answers'   => (int) $u['wrong_answers'],
                'powerups_used'   => (int) $u['powerups_used'],
            ],
            'achievements' => $this->achievements->forUser($userId),
            'learning' => $this->learning->summary($userId),
            'weekly' => $this->weekly->lastWeek($userId),
            'wallet' => $wallet,
            'xp_chart' => $chart,
            'daily' => [
                'checked_in' => $u['last_checkin'] === $today->format('Y-m-d'),
                'spins_left' => max(0, GameRules::SPINS_PER_DAY - $this->ledger->countToday($userId, 'spin')),
                'spin_cost'  => GameRules::SPIN_COST,
                'streak_freezes' => $wallet['inventory']['streakFreeze'] ?? 0,
            ],
        ];
    }

    // Only username, email and avatar can be edited. Coins/XP/level/streak
    // can't be set from the browser any more.
    public function update(int $userId, array $input): array
    {
        $u = $this->users->find($userId);
        $username = trim((string) ($input['username'] ?? $u['username']));
        $email = trim((string) ($input['email'] ?? $u['email']));
        $avatar = array_key_exists('avatar', $input) ? $input['avatar'] : $u['avatar'];

        AuthService::validateUsername($username);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw ApiError::badRequest('INVALID_EMAIL', 'Invalid email format');
        if ($this->users->usernameOrEmailTaken($username, $email, $userId)) {
            throw ApiError::conflict('TAKEN', 'Username or email already in use');
        }
        $avatar = $this->validateAvatar($avatar);

        $this->users->updateProfile($userId, $username, $email, $avatar);
        $_SESSION['username'] = $username;
        return $this->me($userId);
    }

    private function validateAvatar(mixed $avatar): ?string
    {
        if ($avatar === null || $avatar === '') return null;
        if (!is_string($avatar)) throw ApiError::badRequest('INVALID_AVATAR', 'Invalid avatar');
        if (preg_match('#^https?://[^\s"<>]{1,500}$#i', $avatar)) return $avatar;
        // SVG is allowed: avatars are only ever rendered via <img>, where SVG can't run scripts.
        if (preg_match('#^data:image/(png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $avatar)) {
            if (strlen($avatar) > self::MAX_AVATAR_BYTES) {
                throw ApiError::badRequest('AVATAR_TOO_LARGE', 'Image is too large (max ~200 KB)');
            }
            return $avatar;
        }
        throw ApiError::badRequest('INVALID_AVATAR', 'Avatar must be an image URL or an uploaded PNG/JPEG/GIF/WebP/SVG');
    }
}
