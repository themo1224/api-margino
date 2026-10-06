<?php

namespace App\Actions;

use App\Enums\RivalMatchStatus;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\Shop;
use App\Rivals\Contracts\RivalMatchAdvisor;
use App\Rivals\RivalCatalogRegistry;
use Illuminate\Support\Carbon;

/**
 * Auto-discover rival listings after sync (no seller URL paste).
 */
class DiscoverProductRivals
{
    public function __construct(
        private readonly RivalCatalogRegistry $registry,
        private readonly RivalMatchAdvisor $advisor,
        private readonly RefreshProductRivalSnapshot $refreshSnapshot,
    ) {}

    public function handle(Product $product): void
    {
        $product->loadMissing('shop.plan');
        $shop = $product->shop;

        if (! $this->withinPlanLimit($shop, $product)) {
            return;
        }

        $existingLinked = ProductRivalMatch::query()
            ->whereBelongsTo($product)
            ->get()
            ->first(fn (ProductRivalMatch $match): bool => $match->isLinked());

        if ($existingLinked !== null) {
            $this->refreshSnapshot->handle($product, $existingLinked);

            return;
        }

        $rejected = ProductRivalMatch::query()
            ->whereBelongsTo($product)
            ->where('status', RivalMatchStatus::Rejected)
            ->exists();

        if ($rejected) {
            return;
        }

        $pick = null;
        $source = null;

        foreach ($this->registry->enabledClients() as $client) {
            foreach ($this->advisor->searchQueries($product) as $query) {
                $candidates = $client->search($query);
                $candidatePick = $this->advisor->pickBest($product, $candidates, $query);
                if ($candidatePick === null) {
                    continue;
                }

                if ($pick === null || $candidatePick->confidence > $pick->confidence) {
                    $pick = $candidatePick;
                    $source = $client->source();
                }
            }
        }

        if ($pick === null || $source === null) {
            $product->fill(['rivals_stale' => true])->save();

            return;
        }

        $autoMin = (float) config('rivals.auto_link_min_confidence', 0.78);
        $status = $pick->confidence >= $autoMin
            ? RivalMatchStatus::AutoLinked
            : RivalMatchStatus::NeedsConfirm;

        $match = ProductRivalMatch::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'source' => $source,
            ],
            [
                'listing_identity' => $pick->candidate->listingIdentity,
                'listing_title' => $pick->candidate->title,
                'listing_url' => $pick->candidate->url,
                'search_query' => $pick->searchQuery,
                'confidence' => number_format($pick->confidence, 4, '.', ''),
                'status' => $status,
                'confirmed_at' => $status === RivalMatchStatus::AutoLinked ? Carbon::now() : null,
            ],
        );

        if ($status === RivalMatchStatus::AutoLinked) {
            $this->refreshSnapshot->handle($product->fresh(), $match->fresh());
        } else {
            $product->fill(['rivals_stale' => true])->save();
        }
    }

    private function withinPlanLimit(Shop $shop, Product $product): bool
    {
        $max = (int) ($shop->plan->max_rival_products ?? 50);
        if ($max <= 0) {
            return false;
        }

        $otherLinked = ProductRivalMatch::query()
            ->whereHas('product', fn ($q) => $q->where('shop_id', $shop->id)->where('id', '!=', $product->id))
            ->where('status', RivalMatchStatus::AutoLinked)
            ->count();

        $hasOwn = ProductRivalMatch::query()->whereBelongsTo($product)->exists();

        return $hasOwn || $otherLinked < $max;
    }
}
