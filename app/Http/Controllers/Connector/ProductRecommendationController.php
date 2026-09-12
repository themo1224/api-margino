<?php

namespace App\Http\Controllers\Connector;

use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRecommendationController extends Controller
{
    public function __invoke(Request $request, string $external_id): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        $product = Product::query()
            ->whereBelongsTo($shop)
            ->where('external_id', $external_id)
            ->first();

        if ($product === null || $product->recommended_price === null || $product->recommendation_updated_at === null) {
            throw new ProductNotFoundException;
        }

        return response()->json([
            'external_id' => $product->external_id,
            'recommended_price' => $product->recommended_price,
            'currency' => $product->currency,
            'below_floor' => $product->below_floor,
            'floor_price' => $product->floor_price,
            'updated_at' => $product->recommendation_updated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
