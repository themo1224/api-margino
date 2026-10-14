<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Actions\RecomputeShopRecommendations;
use App\Actions\RefreshProductRivalSnapshot;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchRefreshRivalsController extends Controller
{
    public function __invoke(
        Request $request,
        EnsureUserShop $ensureUserShop,
        RefreshProductRivalSnapshot $refreshSnapshot,
        RecomputeShopRecommendations $recompute,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        $validated = $request->validate([
            'productIds' => ['required', 'array'],
            'productIds.*' => ['required'],
        ]);

        $productIds = $validated['productIds'];

        $products = Product::query()
            ->whereBelongsTo($shop)
            ->whereIn('id', $productIds)
            ->get();

        foreach ($products as $product) {
            $refreshSnapshot->handle($product);
        }

        $recompute->handle($shop->fresh(['costProfile', 'plan']));

        $updatedRows = [];
        foreach ($products as $product) {
            $product->refresh()->loadMissing([
                'rivalMatches',
                'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
            ]);
            $updatedRows[] = SellerProductRivalMapper::toArray($product);
        }

        return response()->json([
            'status' => 200,
            'data' => $updatedRows,
        ]);
    }
}
