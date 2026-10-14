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

class BatchApplyPriceController extends Controller
{
    public function __invoke(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
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

        $updatedRows = [];

        foreach ($products as $product) {
            $newPrice = (string) ($product->recommended_price ?? $product->price);

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

            $updatedRows[] = SellerProductRivalMapper::toArray($product);
        }

        return response()->json([
            'status' => 200,
            'data' => $updatedRows,
        ]);
    }
}
