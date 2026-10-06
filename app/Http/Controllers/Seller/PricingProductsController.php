<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingProductsController extends Controller
{
    public function __invoke(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
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
            ->map(fn (Product $product): array => SellerProductRivalMapper::toArray($product))
            ->values();

        return response()->json([
            'status' => 200,
            'data' => $products,
        ]);
    }
}
