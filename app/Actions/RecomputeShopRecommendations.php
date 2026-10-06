<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecomputeShopRecommendations
{
    public function __construct(
        private readonly ComputeProductRecommendation $compute,
    ) {}

    /**
     * Recompute recommendations for every product in the shop.
     * With a cost profile: floor-aware (+ rival-aware when snapshots are fresh).
     * Without: B4 stub if allowed, otherwise clear recommendation fields.
     */
    public function handle(Shop $shop): void
    {
        $shop->loadMissing(['costProfile', 'plan']);
        $profile = $shop->costProfile;
        $products = Product::query()
            ->whereBelongsTo($shop)
            ->with(['rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at')])
            ->get();

        if ($profile === null) {
            $this->applyWithoutProfile($products);

            return;
        }

        $count = $products->count();
        $divisor = (string) max($count, 1);
        $allocated = Decimal::div($profile->totalOverhead(), $divisor);

        foreach ($products as $product) {
            $product->setRelation('shop', $shop);
            $fields = $this->compute->handle($product, $profile, $allocated);
            $product->fill($fields)->save();
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function applyWithoutProfile($products): void
    {
        $stubAllowed = (bool) config('connector.stub_recommendations');
        $now = Carbon::now();

        foreach ($products as $product) {
            if ($stubAllowed) {
                $product->fill([
                    'recommended_price' => $product->price,
                    'below_floor' => false,
                    'floor_price' => null,
                    'cannot_match_profitably' => false,
                    'rivals_stale' => true,
                    'recommendation_updated_at' => $now,
                ])->save();

                continue;
            }

            $product->fill([
                'recommended_price' => null,
                'below_floor' => false,
                'floor_price' => null,
                'cannot_match_profitably' => false,
                'rivals_stale' => true,
                'recommendation_updated_at' => null,
            ])->save();
        }
    }
}
