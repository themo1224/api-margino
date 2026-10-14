<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Enums\AppliedSource;
use App\Http\Controllers\Controller;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplyProductPriceController extends Controller
{
    public function __invoke(Product $product, Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());

        if ($product->shop_id !== $shop->id) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found',
            ], 404);
        }

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $newPrice = isset($validated['price']) && (float) $validated['price'] > 0
            ? (string) $validated['price']
            : (string) ($product->recommended_price ?? $product->price);

        $product->update([
            'price' => $newPrice,
            'last_applied_price' => $newPrice,
        ]);

        AppliedPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'applied_price' => $newPrice,
            'currency' => $product->currency ?: 'IRT',
            'source' => AppliedSource::Manual,
            'applied_at' => now(),
        ]);

        $product->loadMissing([
            'rivalMatches',
            'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
        ]);

        return response()->json([
            'status' => 200,
            'data' => SellerProductRivalMapper::toArray($product),
        ]);
    }
}
