<?php

namespace App\Support;

use App\Enums\RivalMatchStatus;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;

final class SellerProductRivalMapper
{
    /**
     * Map a product (+ relations) to the Vite panel ProductRivalRow shape.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     sku: string,
     *     storePrice: float,
     *     costFloor: float,
     *     recommendedPrice: float,
     *     rivalSource: string|null,
     *     rivalCheapest: float|null,
     *     rivalMedian: float|null,
     *     rivalCount: int,
     *     vsStorePct: float|null,
     *     rivalStatus: string,
     *     lastRivalAt: string|null,
     *     pendingRivalMatch: array{id: string, source: string, listingTitle: string|null, confidence: float}|null
     * }
     */
    public static function toArray(Product $product): array
    {
        /** @var RivalSnapshot|null $snapshot */
        $snapshot = $product->rivalSnapshots->first();

        /** @var ProductRivalMatch|null $pending */
        $pending = $product->rivalMatches->first(
            fn (ProductRivalMatch $match): bool => $match->status === RivalMatchStatus::NeedsConfirm
                && $match->confirmed_at === null
        );

        $storePrice = self::toFloat($product->price);
        $cheapest = $snapshot !== null ? self::toFloat($snapshot->cheapest_price) : null;
        $median = $snapshot !== null ? self::toFloat($snapshot->median_price) : null;

        $vsStorePct = null;
        if ($cheapest !== null && $cheapest > 0.0) {
            $vsStorePct = round((($storePrice - $cheapest) / $cheapest) * 100, 2);
        }

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'sku' => (string) ($product->sku ?? ''),
            'storePrice' => $storePrice,
            'costFloor' => self::toFloat($product->floor_price ?? '0'),
            'recommendedPrice' => self::toFloat($product->recommended_price ?? $product->price),
            'rivalSource' => $snapshot?->source->value ?? $pending?->source->value,
            'rivalCheapest' => $cheapest,
            'rivalMedian' => $median,
            'rivalCount' => $snapshot !== null ? (int) $snapshot->competitor_count : 0,
            'vsStorePct' => $vsStorePct,
            'rivalStatus' => self::rivalStatus($product, $snapshot, $pending),
            'lastRivalAt' => $snapshot?->captured_at?->toIso8601String(),
            'pendingRivalMatch' => $pending === null ? null : [
                'id' => (string) $pending->id,
                'source' => $pending->source->value,
                'listingTitle' => $pending->listing_title,
                'confidence' => round((float) $pending->confidence, 4),
            ],
        ];
    }

    private static function rivalStatus(
        Product $product,
        ?RivalSnapshot $snapshot,
        ?ProductRivalMatch $pending,
    ): string {
        if ($product->cannot_match_profitably) {
            return 'cannot_match';
        }

        if ($pending !== null && $snapshot === null) {
            return 'needs_confirm';
        }

        if ($snapshot !== null) {
            return $product->rivals_stale ? 'stale' : 'matched';
        }

        return 'finding';
    }

    private static function toFloat(string $value): float
    {
        return (float) $value;
    }
}
