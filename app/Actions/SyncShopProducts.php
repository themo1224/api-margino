<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class SyncShopProducts
{
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

            // Stub engine until B6: recommended_price copies the synced store price.
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
                    'recommended_price' => $item['price'],
                    'below_floor' => false,
                    'floor_price' => null,
                    'recommendation_updated_at' => $now,
                ],
            );

            $accepted[] = $externalId;
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
