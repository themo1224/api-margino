<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingPriceSyncController extends Controller
{
    /**
     * Retrieve all products with approved prices waiting to sync to WooCommerce.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        $pendingProducts = Product::query()
            ->whereBelongsTo($shop)
            ->where('pending_price_sync', true)
            ->orderBy('id')
            ->get();

        $items = $pendingProducts->map(fn (Product $p): array => [
            'external_id' => (string) $p->external_id,
            'sku' => (string) $p->sku,
            'price' => (string) ($p->pending_price ?: $p->price),
            'currency' => (string) ($p->currency ?: 'IRT'),
            'updated_at' => $p->updated_at ? $p->updated_at->toISOString() : now()->toISOString(),
        ]);

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    /**
     * Acknowledge that WooCommerce successfully updated the prices.
     */
    public function ack(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        $validated = $request->validate([
            'synced_external_ids' => ['required', 'array'],
            'synced_external_ids.*' => ['required'],
        ]);

        $externalIds = array_map('strval', $validated['synced_external_ids']);

        $updatedCount = Product::query()
            ->whereBelongsTo($shop)
            ->whereIn('external_id', $externalIds)
            ->update([
                'pending_price_sync' => false,
                'pending_price' => null,
            ]);

        return response()->json([
            'ok' => true,
            'acknowledged' => $updatedCount,
        ]);
    }
}
