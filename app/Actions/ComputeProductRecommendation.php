<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ShopCostProfile;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

class ComputeProductRecommendation
{
    /**
     * Floor-plus-margin recommendation for one product.
     *
     * @return array{
     *     recommended_price: string,
     *     floor_price: string,
     *     below_floor: bool,
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

        // effective = cost_floor * (1 + margin_pct/100)
        $marginFactor = Decimal::add('1', Decimal::div($marginPct, '100'));
        $effective = Decimal::mul($costFloor, $marginFactor);

        $recommended = $effective;
        if ($product->max_price !== null && $product->max_price !== '') {
            $max = Decimal::normalize($product->max_price);
            // Cap only when max is at or above the floor; floor always wins.
            if (Decimal::compare($max, $effective) >= 0) {
                $recommended = Decimal::min($recommended, $max);
            }
        }

        $belowFloor = Decimal::compare($product->price, $effective) < 0;

        return [
            'recommended_price' => $recommended,
            'floor_price' => $effective,
            'below_floor' => $belowFloor,
            'recommendation_updated_at' => Carbon::now(),
        ];
    }
}
