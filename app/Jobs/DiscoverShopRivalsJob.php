<?php

namespace App\Jobs;

use App\Actions\DiscoverProductRivals;
use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverShopRivalsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $shopId,
    ) {}

    public function handle(
        DiscoverProductRivals $discover,
        RecomputeShopRecommendations $recompute,
        EvaluateShopAlerts $evaluateAlerts,
    ): void {
        $shop = Shop::query()->with('plan')->find($this->shopId);
        if ($shop === null) {
            return;
        }

        $max = (int) ($shop->plan->max_rival_products ?? 50);
        $products = Product::query()
            ->whereBelongsTo($shop)
            ->orderBy('id')
            ->limit(max($max, 0))
            ->get();

        foreach ($products as $product) {
            $product->setRelation('shop', $shop);
            $discover->handle($product);
        }

        $fresh = $shop->fresh(['costProfile', 'plan', 'user', 'products']);
        $recompute->handle($fresh);
        $evaluateAlerts->handle($fresh->fresh(['costProfile', 'user', 'products']));
    }
}
