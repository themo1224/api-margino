<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use App\Rivals\RivalCatalogRegistry;

class RefreshProductRivalSnapshot
{
    public function __construct(
        private readonly RivalCatalogRegistry $registry,
    ) {}

    public function handle(Product $product, ?ProductRivalMatch $match = null): ?RivalSnapshot
    {
        $match ??= ProductRivalMatch::query()
            ->whereBelongsTo($product)
            ->get()
            ->first(fn (ProductRivalMatch $row): bool => $row->isLinked());

        if ($match === null || ! $match->isLinked()) {
            $product->fill(['rivals_stale' => true])->save();

            return null;
        }

        $client = $this->registry->clientFor($match->source);
        $report = $client->fetchPrices($match->listing_identity);

        if ($report === null) {
            $product->fill(['rivals_stale' => true])->save();

            return null;
        }

        $snapshot = RivalSnapshot::query()->create([
            'product_id' => $product->id,
            'source' => $match->source,
            'listing_identity' => $report->listingIdentity,
            'listing_title' => $report->listingTitle ?? $match->listing_title,
            'cheapest_price' => $report->cheapestPrice,
            'median_price' => $report->medianPrice,
            'competitor_count' => $report->competitorCount,
            'currency' => $report->currency,
            'captured_at' => now(),
        ]);

        $product->fill(['rivals_stale' => false])->save();

        return $snapshot;
    }
}
