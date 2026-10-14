<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\RivalMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlinkProductRivalController extends Controller
{
    public function __invoke(
        Product $product,
        Request $request,
        EnsureUserShop $ensureUserShop,
        RecomputeShopRecommendations $recompute,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        abort_unless($product->shop_id === $shop->id, 404);

        ProductRivalMatch::query()
            ->whereBelongsTo($product)
            ->where('status', '!=', RivalMatchStatus::Rejected)
            ->update([
                'status' => RivalMatchStatus::Rejected,
            ]);

        $product->fill(['rivals_stale' => true])->save();

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
