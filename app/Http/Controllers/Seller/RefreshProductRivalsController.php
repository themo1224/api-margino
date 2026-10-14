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

class RefreshProductRivalsController extends Controller
{
    public function __invoke(
        Product $product,
        Request $request,
        EnsureUserShop $ensureUserShop,
        RefreshProductRivalSnapshot $refreshSnapshot,
        RecomputeShopRecommendations $recompute,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        abort_unless($product->shop_id === $shop->id, 404);

        $refreshSnapshot->handle($product);
        $recompute->handle($shop->fresh(['costProfile', 'plan']));

        $product->refresh()->loadMissing([
            'rivalMatches',
            'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
        ]);

        return response()->json([
            'status' => 200,
            'data' => SellerProductRivalMapper::toArray($product),
        ]);
    }
}
