<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Decimal;

class EvaluateAutoApplySafety
{
    /**
     * Evaluates whether a product is currently safe and eligible for Auto-Apply.
     *
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     recommended_price: string|null,
     *     current_price: string,
     *     drop_percent: string|null
     * }
     */
    public function handle(Product $product, ?Shop $shop = null): array
    {
        $shop = $shop ?? $product->shop;
        if ($shop === null) {
            $product->loadMissing('shop');
            $shop = $product->shop;
        }

        $currentPrice = (string) $product->price;

        // 1. Plan check
        if (! $shop->isAutoApplyAllowed()) {
            return [
                'eligible' => false,
                'reason' => 'plan_not_allowed',
                'recommended_price' => null,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // 2. Shop-level switch check
        if (! $shop->auto_apply_enabled) {
            return [
                'eligible' => false,
                'reason' => 'shop_disabled',
                'recommended_price' => null,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // 3. Product-level exclusion
        if ($product->auto_apply_excluded) {
            return [
                'eligible' => false,
                'reason' => 'product_excluded',
                'recommended_price' => null,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // 4. Recommendation exists
        $rec = $product->recommended_price;
        if ($rec === null || $rec === '') {
            return [
                'eligible' => false,
                'reason' => 'no_recommendation',
                'recommended_price' => null,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        $recommended = Decimal::normalize($rec);
        $normalizedCurrent = Decimal::normalize($currentPrice);

        // 5. Price changed check
        if (Decimal::compare($recommended, $normalizedCurrent) === 0) {
            return [
                'eligible' => false,
                'reason' => 'price_unchanged',
                'recommended_price' => $recommended,
                'current_price' => $currentPrice,
                'drop_percent' => '0',
            ];
        }

        // 6. Cost Floor Safety Law
        if ($product->below_floor || $product->cannot_match_profitably) {
            return [
                'eligible' => false,
                'reason' => 'below_floor',
                'recommended_price' => $recommended,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        if ($product->floor_price === null) {
            return [
                'eligible' => false,
                'reason' => 'floor_missing',
                'recommended_price' => $recommended,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // Verify recommended price is >= floor_price
        if (Decimal::compare($recommended, Decimal::normalize($product->floor_price)) < 0) {
            return [
                'eligible' => false,
                'reason' => 'below_floor',
                'recommended_price' => $recommended,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // 7. Rivals freshness
        if ($shop->auto_apply_only_fresh_rivals && $product->rivals_stale) {
            return [
                'eligible' => false,
                'reason' => 'rivals_stale',
                'recommended_price' => $recommended,
                'current_price' => $currentPrice,
                'drop_percent' => null,
            ];
        }

        // 8. Circuit Breaker (Max Price Drop Protection)
        $dropPercent = null;
        if (Decimal::compare($recommended, $normalizedCurrent) < 0 && Decimal::compare($normalizedCurrent, '0') > 0) {
            $diff = Decimal::sub($normalizedCurrent, $recommended);
            $ratio = Decimal::div($diff, $normalizedCurrent);
            $dropPercent = Decimal::mul($ratio, '100');

            $maxAllowedDrop = (string) ($shop->auto_apply_max_drop_percent ?? '15.00');
            if (Decimal::compare($dropPercent, $maxAllowedDrop) > 0) {
                return [
                    'eligible' => false,
                    'reason' => 'circuit_breaker_drop_exceeded',
                    'recommended_price' => $recommended,
                    'current_price' => $currentPrice,
                    'drop_percent' => $dropPercent,
                ];
            }
        }

        return [
            'eligible' => true,
            'reason' => null,
            'recommended_price' => $recommended,
            'current_price' => $currentPrice,
            'drop_percent' => $dropPercent,
        ];
    }
}
