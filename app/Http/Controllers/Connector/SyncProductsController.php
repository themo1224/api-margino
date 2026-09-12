<?php

namespace App\Http\Controllers\Connector;

use App\Actions\SyncShopProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connector\SyncProductsRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class SyncProductsController extends Controller
{
    public function __invoke(SyncProductsRequest $request, SyncShopProducts $syncShopProducts): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        /** @var list<array{external_id: string, sku?: string|null, name: string, price: string}> $products */
        $products = $request->validated('products');

        return response()->json($syncShopProducts->handle(
            $shop,
            $request->string('currency')->toString(),
            $products,
        ));
    }
}
