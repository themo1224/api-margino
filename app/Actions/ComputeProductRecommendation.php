<?php

namespace App\Actions;

use App\Enums\PricingStrategy;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\ShopCostProfile;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

class ComputeProductRecommendation
{
    /**
     * Floor-aware recommendation. Fresh rival snapshots can shape the suggest
     * (match cheapest / undercut / median) but never below the effective floor.
     * Missing/stale rivals → floor_plus_margin + rivals_stale.
     *
     * @return array{
     *     recommended_price: string,
     *     floor_price: string,
     *     below_floor: bool,
     *     cannot_match_profitably: bool,
     *     rivals_stale: bool,
     *     recommendation_updated_at: Carbon
     * }
     */
    public function handle(Product $product, ShopCostProfile $profile, string $allocatedOverhead): array
    {
        $direct = $product->direct_cost !== null && $product->direct_cost !== ''
            ? Decimal::normalize($product->direct_cost)
            : '0';

        $costFloor = Decimal::add($allocatedOverhead, $direct);

        $marginPct = $product->min_margin_percent !== null && $product->min_margin_percent !== ''
            ? Decimal::normalize($product->min_margin_percent)
            : Decimal::normalize($profile->min_margin_percent);

        $marginFactor = Decimal::add('1', Decimal::div($marginPct, '100'));
        $effective = Decimal::mul($costFloor, $marginFactor);

        $product->loadMissing('shop.plan');
        $freshRival = $this->freshSnapshot($product);
        $strategy = $product->shop?->pricing_strategy ?? PricingStrategy::MatchCheapest;
        $snapshot = $strategy === PricingStrategy::FloorPlusMargin ? null : $freshRival;
        $rivalsStale = $freshRival === null;
        $cannotMatch = false;
        $recommended = $effective;

        if ($snapshot !== null) {
            $target = $this->rivalTargetPrice($product, $snapshot, $strategy);
            if (Decimal::compare($target, $effective) < 0) {
                $recommended = $effective;
                $cannotMatch = true;
            } else {
                $recommended = $target;
            }
        }

        if ($product->max_price !== null && $product->max_price !== '') {
            $max = Decimal::normalize($product->max_price);
            if (Decimal::compare($max, $effective) >= 0) {
                $recommended = Decimal::min($recommended, $max);
            }
        }

        if (Decimal::compare($recommended, $effective) < 0) {
            $recommended = $effective;
        }

        return [
            'recommended_price' => $recommended,
            'floor_price' => $effective,
            'below_floor' => Decimal::compare($product->price, $effective) < 0,
            'cannot_match_profitably' => $cannotMatch,
            'rivals_stale' => $rivalsStale,
            'recommendation_updated_at' => Carbon::now(),
        ];
    }

    private function freshSnapshot(Product $product): ?RivalSnapshot
    {
        $snapshot = $product->relationLoaded('rivalSnapshots')
            ? $product->rivalSnapshots->sortByDesc(fn (RivalSnapshot $s) => $s->captured_at?->timestamp ?? 0)->first()
            : $product->latestRivalSnapshot();

        if ($snapshot === null) {
            return null;
        }

        $refreshHours = (int) ($product->shop?->plan?->rival_refresh_hours ?? 24);
        $staleAfter = (int) ceil($refreshHours * (float) config('rivals.stale_multiplier', 1.5));

        if ($snapshot->captured_at->lt(Carbon::now()->subHours($staleAfter))) {
            return null;
        }

        return $snapshot;
    }

    private function rivalTargetPrice(Product $product, RivalSnapshot $snapshot, PricingStrategy $strategy): string
    {
        $cheapest = Decimal::normalize($snapshot->cheapest_price);
        $median = $snapshot->median_price !== null && $snapshot->median_price !== ''
            ? Decimal::normalize($snapshot->median_price)
            : $cheapest;

        return match ($strategy) {
            PricingStrategy::Median => $median,
            PricingStrategy::UndercutPercent => $this->undercut($cheapest, (string) ($product->shop?->undercut_percent ?? '1')),
            PricingStrategy::FloorPlusMargin, PricingStrategy::MatchCheapest => $cheapest,
        };
    }

    private function undercut(string $cheapest, string $percent): string
    {
        $factor = Decimal::sub('1', Decimal::div(Decimal::normalize($percent), '100'));
        if (Decimal::compare($factor, '0') <= 0) {
            return $cheapest;
        }

        return Decimal::mul($cheapest, $factor);
    }
}
