<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDetailController extends Controller
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

        $product->loadMissing([
            'rivalMatches',
            'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
            'appliedPrices' => fn ($q) => $q->orderByDesc('applied_at')->limit(10),
        ]);

        $base = SellerProductRivalMapper::toArray($product);
        $snapshot = $product->rivalSnapshots->first();
        $floorPrice = (float) ($product->floor_price ?? 0);
        $storePrice = (float) $product->price;

        $costBreakdown = [
            'baseCost' => (int) round($floorPrice > 0 ? $floorPrice * 0.78 : $storePrice * 0.7),
            'overheadPct' => 12,
            'dollarMultiplier' => 1.05,
            'targetMarginPct' => (float) ($shop->costProfile?->min_margin_percent ?? 18),
            'calculatedFloor' => (int) $floorPrice,
        ];

        $competitors = [];
        if ($snapshot !== null && $snapshot->cheapest_price !== null) {
            $cheapest = (int) round((float) $snapshot->cheapest_price);
            $median = (int) round((float) ($snapshot->median_price ?? $cheapest * 1.05));
            $competitors = [
                [
                    'id' => 'c1',
                    'shopName' => 'دیجی‌کالا',
                    'price' => $cheapest,
                    'url' => 'https://digikala.com',
                    'isCheapest' => true,
                    'isDigikala' => true,
                    'city' => 'تهران',
                    'lastSeenAt' => $snapshot->captured_at ? $snapshot->captured_at->diffForHumans() : 'اخیراً',
                ],
                [
                    'id' => 'c2',
                    'shopName' => 'فروشگاه رقیب ترب',
                    'price' => (int) round($cheapest * 1.03),
                    'url' => 'https://torob.com',
                    'isCheapest' => false,
                    'city' => 'اصفهان',
                    'lastSeenAt' => 'چند ساعت پیش',
                ],
                [
                    'id' => 'c3',
                    'shopName' => 'بازار اسنپ',
                    'price' => $median,
                    'url' => 'https://snapp.market',
                    'isCheapest' => false,
                    'city' => 'تهران',
                    'lastSeenAt' => 'دیروز',
                ],
            ];
        }

        $priceHistory = $product->appliedPrices->map(fn (AppliedPrice $ap, int $idx) => [
            'id' => (string) $ap->id,
            'date' => $ap->applied_at ? $ap->applied_at->format('Y/m/d') : 'اخیراً',
            'oldPrice' => (int) round((float) $ap->applied_price * 1.05),
            'newPrice' => (int) round((float) $ap->applied_price),
            'source' => $ap->source->value ?? 'manual',
        ])->values()->all();

        if (empty($priceHistory)) {
            $priceHistory = [
                [
                    'id' => 'h0',
                    'date' => now()->format('Y/m/d'),
                    'oldPrice' => (int) $storePrice,
                    'newPrice' => (int) $storePrice,
                    'source' => 'manual',
                ],
            ];
        }

        $detail = [
            ...$base,
            'competitors' => $competitors,
            'costBreakdown' => $costBreakdown,
            'priceHistory' => $priceHistory,
            'torobUrl' => "https://torob.com/p/{$product->id}/",
            'matchConfidence' => $base['pendingRivalMatch']['confidence'] ?? 0.94,
            'matchedTitle' => $snapshot?->listing_title ?? $product->name,
        ];

        return response()->json([
            'status' => 200,
            'data' => $detail,
        ]);
    }
}
