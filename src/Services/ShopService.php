<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ShopRepository;
use App\Support\ApiError;
use App\Support\Database;

final class ShopService
{
    public function __construct(
        private ShopRepository $shop = new ShopRepository(),
        private ProgressionService $progression = new ProgressionService(),
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
            if ($isCosmetic && ($this->shop->inventory($userId)[$item['code']] ?? 0) > 0) {
                throw ApiError::conflict('ALREADY_OWNED', 'You already own this');
            }
            // Price always comes from the database, never from the request.
            $this->progression->spend($userId, (int) $item['price'], 'purchase', $item['code'] . ':' . bin2hex(random_bytes(6)));
            foreach ($item['grants'] as $grantCode => $qty) {
                $this->shop->grant($userId, $grantCode, (int) $qty);
            }
            return ['purchased' => $item['code'], 'wallet' => $this->progression->wallet($userId)];
        });
    }
}
