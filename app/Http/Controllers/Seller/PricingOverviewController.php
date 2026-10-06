<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Product;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingOverviewController extends Controller
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
            ->get();

        $rows = $products->map(fn (Product $product): array => SellerProductRivalMapper::toArray($product));

        $productsTotal = $rows->count();
        $productsWithRivals = $rows->filter(fn (array $row): bool => $row['rivalCheapest'] !== null)->count();
        $aboveRivalsCount = $rows->filter(fn (array $row): bool => ($row['vsStorePct'] ?? null) !== null && $row['vsStorePct'] > 0)->count();
        $staleCount = $rows->filter(fn (array $row): bool => $row['rivalStatus'] === 'stale')->count();
        $findingCount = $rows->filter(fn (array $row): bool => $row['rivalStatus'] === 'finding')->count();
        $recommendCount = $rows->filter(fn (array $row): bool => $row['recommendedPrice'] > 0)->count();
        $hasCostProfile = $shop->costProfile()->exists();
        $hasActiveKey = ApiKey::query()
            ->whereBelongsTo($shop)
            ->whereNull('revoked_at')
            ->exists();

        $insights = [];
        foreach ($rows->take(5) as $row) {
            if (($row['vsStorePct'] ?? null) !== null && $row['vsStorePct'] > 5) {
                $insights[] = [
                    'id' => 'above-'.$row['id'],
                    'productName' => $row['name'],
                    'message' => 'قیمت فروشگاه بالاتر از ارزان‌ترین رقیب است.',
                    'kind' => 'above_rivals',
                ];
            } elseif ($row['rivalStatus'] === 'stale') {
                $insights[] = [
                    'id' => 'stale-'.$row['id'],
                    'productName' => $row['name'],
                    'message' => 'داده رقیب قدیمی است؛ در حال تازه‌سازی.',
                    'kind' => 'stale',
                ];
            } elseif (($row['costFloor'] ?? 0) > 0 && $row['storePrice'] < $row['costFloor']) {
                $insights[] = [
                    'id' => 'below-'.$row['id'],
                    'productName' => $row['name'],
                    'message' => 'قیمت فروشگاه زیر کف هزینه است.',
                    'kind' => 'below_cost',
                ];
            }
        }

        if ($insights === [] && $productsWithRivals > 0) {
            $insights[] = [
                'id' => 'ok-rivals',
                'productName' => $shop->name,
                'message' => 'تحلیل رقبا برای بخشی از کاتالوگ آماده است.',
                'kind' => 'ok',
            ];
        }

        $rivalHeadline = match (true) {
            $productsTotal === 0 => 'هنوز محصولی همگام نشده — کلید بسازید و از ووکامرس همگام‌سازی کنید.',
            $findingCount > 0 && $productsWithRivals === 0 => 'در حال پیدا کردن رقبا برای محصولات همگام‌شده…',
            $productsWithRivals > 0 => sprintf(
                'برای %d از %d محصول، قیمت رقبا را دیده‌ایم.',
                $productsWithRivals,
                $productsTotal
            ),
            default => 'هنوز رقیبی پیدا نشده؛ پس از همگام‌سازی صبر کنید یا دوباره همگام کنید.',
        };

        return response()->json([
            'status' => 200,
            'data' => [
                'shopName' => $shop->name,
                'costProfileComplete' => $hasCostProfile,
                'costProfileLabel' => $hasCostProfile ? 'پروفایل هزینه تکمیل شده' : 'پروفایل هزینه ناقص',
                'recommendCount' => $recommendCount,
                'productsTotal' => $productsTotal,
                'productsWithRivals' => $productsWithRivals,
                'aboveRivalsCount' => $aboveRivalsCount,
                'staleCount' => $staleCount,
                'rivalHeadline' => $rivalHeadline,
                'insights' => array_slice($insights, 0, 5),
                'hasActiveApiKey' => $hasActiveKey,
                'findingCount' => $findingCount,
            ],
        ]);
    }
}
