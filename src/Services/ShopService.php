<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Repositories\UserRepository;
use App\Support\ApiError;
use App\Support\Database;

final class ShopService
{
    public function __construct(
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
        private UserRepository $users = new UserRepository(),
    ) {}

    public function catalogue(int $userId): array
    {
        $owned = $this->shop->inventory($userId);
        $items = array_map(function ($item) use ($owned) {
            $item['owned'] = $owned[$item['code']] ?? 0;
            return $item;
        }, $this->shop->catalogue());
        return ['items' => $items, 'wallet' => $this->progression->wallet($userId)];
    }

    public function buy(int $userId, string $code): array
    {
        $item = $this->shop->item($code);
        if ($item === null || !$item['active']) throw ApiError::notFound('That item is not for sale');

        return Database::transaction(function () use ($userId, $item) {
            $isCosmetic = in_array($item['kind'], ['skin', 'theme'], true);
            $owned = $this->shop->inventory($userId)[$item['code']] ?? 0;
            if ($isCosmetic && $owned > 0) {
                throw ApiError::conflict('ALREADY_OWNED', 'You already own this');
            }
            if ($item['code'] === 'streakFreeze' && $owned >= GameRules::STREAK_FREEZE_MAX) {
                throw ApiError::conflict('MAX_OWNED', 'You can hold up to ' . GameRules::STREAK_FREEZE_MAX . ' streak freezes');
            }
            // Price always comes from the database, never from the request.
            $this->progression->spend($userId, (int) $item['price'], 'purchase', $item['code'] . ':' . bin2hex(random_bytes(6)));
            foreach ($item['grants'] as $grantCode => $qty) {
                $this->shop->grant($userId, $grantCode, (int) $qty);
            }
            if ($isCosmetic) $this->users->equip($userId, $item['kind'], $item['code']);   // wear it straight away
            return ['purchased' => $item['code'], 'wallet' => $this->progression->wallet($userId)];
        });
    }

    // Wear a skin or use a theme the player owns; null code goes back to the default.
    public function equip(int $userId, string $kind, ?string $code): array
    {
        if (!in_array($kind, ['skin', 'theme'], true)) throw ApiError::badRequest('INVALID_INPUT', "'kind' must be skin or theme");
        if ($code !== null) {
            $item = $this->shop->item($code);
            if ($item === null || $item['kind'] !== $kind) throw ApiError::notFound("No such $kind");
            if (($this->shop->inventory($userId)[$code] ?? 0) < 1) throw ApiError::conflict('NOT_OWNED', "You don't own that yet");
        }
        $this->users->equip($userId, $kind, $code);
        return ['wallet' => $this->progression->wallet($userId)];
    }
}
