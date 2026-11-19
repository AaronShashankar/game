<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Repositories\UserRepository;
use App\Services\ProfileService;
use App\Services\RewardService;
use App\Services\ShopService;

// Profile, rewards, shop and leaderboard endpoints for players.
final class PlayerController
{
    public function __construct(
        private ProfileService $profile = new ProfileService(),
        private RewardService $rewards = new RewardService(),
        private ShopService $shop = new ShopService(),
    ) {}

    public function me(Request $r): array            { return $this->profile->me(Auth::playerId()); }
    public function updateMe(Request $r): array      { return $this->profile->update(Auth::playerId(), [
                                                           'username' => $r->get('username'), 'email' => $r->get('email'),
                                                       ] + ($r->get('avatar') !== null ? ['avatar' => $r->get('avatar')] : [])); }
    public function checkin(Request $r): array       { return $this->rewards->checkin(Auth::playerId()); }
    public function spin(Request $r): array          { return $this->rewards->spin(Auth::playerId()); }
    public function shop(Request $r): array          { return $this->shop->catalogue(Auth::playerId()); }
    public function buy(Request $r): array           { return $this->shop->buy(Auth::playerId(), $r->required('item')); }

    // Public: used on the landing page too.
    public function leaderboard(Request $r): array
    {
        $type = $r->string('type', 'xp');
        $limit = max(1, min(100, $r->int('limit', 10)));
        $users = new UserRepository();
        $rows = $type === 'weekly'
            ? $users->weeklyLeaderboard($limit)
            : $users->leaderboard($type === 'level' ? 'xp' : $type, $limit);

        $rank = 0;
        $board = array_map(function ($row) use (&$rank) {
            return [
                'rank'         => ++$rank,
                'username'     => $row['username'],
                'level'        => (int) $row['level'],
                'xp'           => (int) $row['xp'],
                'coins'        => (int) $row['coins'],
                'streak'       => (int) $row['streak'],
                'avatar'       => $row['avatar'] ?: null,
                'games_played' => (int) $row['total_games'],
            ];
        }, $rows);
        return ['type' => $type, 'leaderboard' => $board];
    }
}
