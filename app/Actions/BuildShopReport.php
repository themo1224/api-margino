<?php

namespace App\Actions;

use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Thin seller report: below floor, above rival, apply rate.
 */
class BuildShopReport
{
    /**
     * @return array{
     *     days: int,
     *     from: string,
     *     to: string,
     *     below_floor: array{count: int, products: list<array<string, mixed>>},
     *     above_rival: array{count: int, products: list<array<string, mixed>>},
     *     apply_rate: array{recommendations: int, applies: int, percent: float|null}
     * }
     */
    public function handle(Shop $shop, int $days = 30): array
    {
        $days = in_array($days, [7, 30], true) ? $days : 30;
        $from = now()->subDays($days)->startOfDay();
        $to = now();

        $products = $shop->products()->with('rivalSnapshots')->get();

        $belowFloor = $products
            ->filter(fn (Product $p): bool => (bool) $p->below_floor)
            ->values()
            ->map(fn (Product $p): array => $this->productRow($p))
            ->all();

        $aboveRival = $products
            ->filter(function (Product $p): bool {
                $snapshot = $this->latestFreshSnapshot($p);
                if ($snapshot === null) {
                    return false;
                }

                return Decimal::compare($p->price, $snapshot->cheapest_price) > 0;
            })
            ->values()
            ->map(function (Product $p): array {
                $snapshot = $this->latestFreshSnapshot($p);

                return $this->productRow($p, $snapshot);
            })
            ->all();

        $recommendationsInRange = $products
            ->filter(function (Product $p) use ($from): bool {
                return $p->recommended_price !== null
                    && $p->recommendation_updated_at !== null
                    && $p->recommendation_updated_at->gte($from);
            })
            ->count();

        $appliesInRange = AppliedPrice::query()
            ->whereBelongsTo($shop)
            ->where('applied_at', '>=', $from)
            ->count();

        $percent = $recommendationsInRange > 0
            ? round(($appliesInRange / $recommendationsInRange) * 100, 1)
            : null;

        return [
            'days' => $days,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'below_floor' => [
                'count' => count($belowFloor),
                'products' => $belowFloor,
            ],
            'above_rival' => [
                'count' => count($aboveRival),
                'products' => $aboveRival,
            ],
            'apply_rate' => [
                'recommendations' => $recommendationsInRange,
                'applies' => $appliesInRange,
                'percent' => $percent,
            ],
        ];
    }

    private function latestFreshSnapshot(Product $product): ?RivalSnapshot
    {
        /** @var Collection<int, RivalSnapshot> $snapshots */
        $snapshots = $product->rivalSnapshots
            ->sortByDesc(fn (RivalSnapshot $s) => $s->captured_at?->timestamp ?? 0)
            ->values();

        $latest = $snapshots->first();
        if ($latest === null || $latest->isStale()) {
            return null;
        }

        return $latest;
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product, ?RivalSnapshot $snapshot = null): array
    {
        return [
            'id' => $product->id,
            'external_id' => $product->external_id,
            'name' => $product->name,
            'price' => $product->price,
            'floor_price' => $product->floor_price,
            'recommended_price' => $product->recommended_price,
            'below_floor' => (bool) $product->below_floor,
            'rival_cheapest' => $snapshot?->cheapest_price,
        ];
    }
}
