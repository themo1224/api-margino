<?php

namespace App\Jobs;

use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Actions\RefreshProductRivalSnapshot;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshShopRivalSnapshotsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $shopId) {}

    public function handle(
        RefreshProductRivalSnapshot $refresh,
        RecomputeShopRecommendations $recompute,
        EvaluateShopAlerts $evaluateAlerts,
    ): void {
        $shop = Shop::query()->with(['plan', 'costProfile', 'user'])->find($this->shopId);
        if ($shop === null) {
            return;
        }

        $hours = (int) ($shop->plan->rival_refresh_hours ?? 24);
        $limit = (int) ($shop->plan->max_rival_products ?? 50);

        $productIds = Product::query()
            ->whereBelongsTo($shop)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $matches = ProductRivalMatch::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->filter(fn (ProductRivalMatch $match): bool => $match->isLinked());

        foreach ($matches as $match) {
            $product = $match->product;
            if ($product === null) {
                continue;
            }

            $latest = $product->rivalSnapshots()->orderByDesc('captured_at')->first();
            if ($latest !== null && $latest->captured_at->gt(now()->subHours($hours))) {
                continue;
            }

            $refresh->handle($product, $match);
        }

        $recompute->handle($shop->fresh(['costProfile']));
        $evaluateAlerts->handle($shop->fresh(['costProfile', 'user', 'products']));
    }
}
