<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ConfirmProductRivalMatch;
use App\Actions\EnsureUserShop;
use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\RivalMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Models\RivalSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, EnsureUserShop $ensureUserShop): Response
    {
        $shop = $ensureUserShop->handle($request->user());

        $products = Product::query()
            ->whereBelongsTo($shop)
            ->with([
                'rivalMatches',
                'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product): array {
                /** @var RivalSnapshot|null $snapshot */
                $snapshot = $product->rivalSnapshots->first();
                /** @var ProductRivalMatch|null $pending */
                $pending = $product->rivalMatches->first(
                    fn (ProductRivalMatch $m): bool => $m->status === RivalMatchStatus::NeedsConfirm && $m->confirmed_at === null
                );

                return [
                    'id' => $product->id,
                    'external_id' => $product->external_id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'currency' => $product->currency,
                    'floor_price' => $product->floor_price,
                    'recommended_price' => $product->recommended_price,
                    'below_floor' => $product->below_floor,
                    'rivals_stale' => $product->rivals_stale,
                    'cannot_match_profitably' => $product->cannot_match_profitably,
                    'direct_cost' => $product->direct_cost,
                    'min_margin_percent' => $product->min_margin_percent,
                    'max_price' => $product->max_price,
                    'last_synced_at' => $product->last_synced_at?->toIso8601String(),
                    'rival' => $snapshot === null ? null : [
                        'source' => $snapshot->source->value,
                        'cheapest_price' => $snapshot->cheapest_price,
                        'median_price' => $snapshot->median_price,
                        'competitor_count' => $snapshot->competitor_count,
                        'captured_at' => $snapshot->captured_at->toIso8601String(),
                        'listing_title' => $snapshot->listing_title,
                    ],
                    'pending_rival_match' => $pending === null ? null : [
                        'id' => $pending->id,
                        'source' => $pending->source->value,
                        'listing_title' => $pending->listing_title,
                        'confidence' => $pending->confidence,
                    ],
                ];
            });

        return Inertia::render('dashboard/products', [
            'products' => $products,
            'hasCostProfile' => $shop->costProfile()->exists(),
            'isEmpty' => $products->isEmpty(),
        ]);
    }

    public function updateCost(
        Request $request,
        Product $product,
        EnsureUserShop $ensureUserShop,
        RecomputeShopRecommendations $recompute,
        EvaluateShopAlerts $evaluateAlerts,
    ): RedirectResponse {
        $shop = $ensureUserShop->handle($request->user());

        abort_unless($product->shop_id === $shop->id, 404);

        $validated = $request->validate([
            'direct_cost' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'min_margin_percent' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'max_price' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
        ]);

        DB::transaction(function () use ($product, $validated, $shop, $recompute): void {
            $product->fill([
                'direct_cost' => $validated['direct_cost'] ?? null,
                'min_margin_percent' => $validated['min_margin_percent'] ?? null,
                'max_price' => $validated['max_price'] ?? null,
            ])->save();

            $recompute->handle($shop->fresh(['costProfile']));
        });

        $evaluateAlerts->handle($shop->fresh(['costProfile', 'user', 'products']));

        return redirect()
            ->route('dashboard.products')
            ->with('status', 'product_cost_saved');
    }

    public function confirmRival(
        Request $request,
        Product $product,
        ProductRivalMatch $match,
        EnsureUserShop $ensureUserShop,
        ConfirmProductRivalMatch $confirm,
    ): RedirectResponse {
        $shop = $ensureUserShop->handle($request->user());
        $confirm->handle($shop, $product, $match);

        return redirect()
            ->route('dashboard.products')
            ->with('status', 'rival_match_confirmed');
    }
}
