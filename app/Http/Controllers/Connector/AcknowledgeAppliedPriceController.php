<?php

namespace App\Http\Controllers\Connector;

use App\Actions\RecordAppliedPrice;
use App\Exceptions\ProductNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connector\AcknowledgeAppliedPriceRequest;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class AcknowledgeAppliedPriceController extends Controller
{
    public function __invoke(
        AcknowledgeAppliedPriceRequest $request,
        RecordAppliedPrice $recordAppliedPrice,
        string $external_id,
    ): JsonResponse {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        $product = Product::query()
            ->whereBelongsTo($shop)
            ->where('external_id', $external_id)
            ->first();

        if ($product === null) {
            throw new ProductNotFoundException;
        }

        /** @var array{applied_price: string, currency: string, source: string, applied_at: string} $payload */
        $payload = $request->safe()->only(['applied_price', 'currency', 'source', 'applied_at']);

        $applied = $recordAppliedPrice->handle($shop, $product, $payload);

        return response()->json([
            'ok' => true,
            'external_id' => $product->external_id,
            'applied_price' => $applied->applied_price,
        ]);
    }
}
