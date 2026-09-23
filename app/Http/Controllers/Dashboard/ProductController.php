<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\EnsureUserShop;
use App\Actions\RecomputeShopRecommendations;
use App\Http\Controllers\Controller;
use App\Models\Product;
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
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'external_id' => $product->external_id,
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => $product->price,
                'currency' => $product->currency,
                'floor_price' => $product->floor_price,
                'recommended_price' => $product->recommended_price,
                'below_floor' => $product->below_floor,
                'direct_cost' => $product->direct_cost,
                'min_margin_percent' => $product->min_margin_percent,
                'max_price' => $product->max_price,
                'last_synced_at' => $product->last_synced_at?->toIso8601String(),
            ]);

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

        return redirect()
            ->route('dashboard.products')
            ->with('status', 'product_cost_saved');
    }
}
