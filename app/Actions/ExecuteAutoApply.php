<?php

namespace App\Actions;

use App\Enums\AppliedSource;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class ExecuteAutoApply
{
    /**
     * Executes the automatic price application for an eligible product.
     */
    public function handle(Product $product, string $newPrice, ?Shop $shop = null): Product
    {
        $shop = $shop ?? $product->shop;
        if ($shop === null) {
            $product->loadMissing('shop');
            $shop = $product->shop;
        }

        $now = Carbon::now();

        $product->update([
            'price' => $newPrice,
            'last_applied_price' => $newPrice,
            'pending_price_sync' => true,
            'pending_price' => $newPrice,
            'last_auto_applied_at' => $now,
        ]);

        AppliedPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'applied_price' => $newPrice,
            'currency' => $product->currency ?: 'IRT',
            'source' => AppliedSource::Auto,
            'applied_at' => $now,
        ]);

        return $product;
    }
}
