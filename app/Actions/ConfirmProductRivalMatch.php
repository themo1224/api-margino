<?php

namespace App\Actions;

use App\Enums\RivalMatchStatus;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class ConfirmProductRivalMatch
{
    public function __construct(
        private readonly RefreshProductRivalSnapshot $refreshSnapshot,
        private readonly RecomputeShopRecommendations $recompute,
        private readonly EvaluateShopAlerts $evaluateAlerts,
    ) {}

    public function handle(Shop $shop, Product $product, ProductRivalMatch $match): void
    {
        abort_unless($product->shop_id === $shop->id, 404);
        abort_unless($match->product_id === $product->id, 404);

        DB::transaction(function () use ($shop, $product, $match): void {
            $match->fill([
                'status' => RivalMatchStatus::AutoLinked,
                'confirmed_at' => now(),
            ])->save();

            $this->refreshSnapshot->handle($product->fresh(), $match->fresh());
            $this->recompute->handle($shop->fresh(['costProfile']));
        });

        $this->evaluateAlerts->handle($shop->fresh(['costProfile', 'user']));
    }
}
