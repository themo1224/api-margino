<?php

namespace App\Http\Controllers\Seller;

use App\Actions\ConfirmProductRivalMatch;
use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfirmRivalMatchController extends Controller
{
    public function __invoke(
        Request $request,
        Product $product,
        ProductRivalMatch $match,
        EnsureUserShop $ensureUserShop,
        ConfirmProductRivalMatch $confirm,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        abort_unless($product->shop_id === $shop->id, 404);
        abort_unless($match->product_id === $product->id, 404);

        $confirm->handle($shop, $product, $match);

        $product->refresh()->load([
            'rivalMatches',
            'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
        ]);

        return response()->json([
            'status' => 200,
            'data' => SellerProductRivalMapper::toArray($product),
        ]);
    }
}
