<?php

namespace App\Actions;

use App\Enums\AppliedSource;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\Shop;

class RecordAppliedPrice
{
    /**
     * @param  array{applied_price: string, currency: string, source: string, applied_at: string}  $payload
     */
    public function handle(Shop $shop, Product $product, array $payload): AppliedPrice
    {
        $applied = AppliedPrice::query()->create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'applied_price' => $payload['applied_price'],
            'currency' => $payload['currency'],
            'source' => AppliedSource::Manual,
            'applied_at' => $payload['applied_at'],
        ]);

        $product->update([
            'last_applied_price' => $payload['applied_price'],
        ]);

        return $applied;
    }
}
