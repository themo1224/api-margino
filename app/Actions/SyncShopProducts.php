<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class SyncShopProducts
{
    public function __construct(
        private readonly RecomputeShopRecommendations $recompute,
    ) {}

    /**
     * @param  list<array{external_id: string, sku?: string|null, name: string, price: string}>  $products
     * @return array{synced: int, accepted: list<string>, rejected: list<array{external_id: string, code: string, message: string}>}
     */
    public function handle(Shop $shop, string $currency, array $products): array
    {
        $accepted = [];
        $rejected = [];

        foreach ($products as $item) {
            $externalId = $item['external_id'];

            if (! $this->isValidPrice($item['price'])) {
                $rejected[] = [
                    'external_id' => $externalId,
                    'code' => 'invalid_price',
                    'message' => 'Price must be a non-negative decimal string.',
                ];

                continue;
            }

            $now = Carbon::now();

            Product::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'external_id' => $externalId,
                ],
                [
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'currency' => $currency,
                    'last_synced_at' => $now,
                ],
            );

            $accepted[] = $externalId;
        }

        if ($accepted !== []) {
            $this->recompute->handle($shop);
        }

        return [
            'synced' => count($accepted),
            'accepted' => $accepted,
            'rejected' => $rejected,
        ];
    }

    private function isValidPrice(string $price): bool
    {
        return preg_match('/^\d+(\.\d+)?$/', $price) === 1;
    }
}
