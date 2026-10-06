<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\RivalSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingReportsController extends Controller
{
    private const DAYS = 28;

    private const WEEKS = 4;

    public function __invoke(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());
        $from = now()->subDays(self::DAYS)->startOfDay();

        $products = Product::query()
            ->whereBelongsTo($shop)
            ->with(['rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at')])
            ->get();

        $snapshots = RivalSnapshot::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->where('captured_at', '>=', $from)
            ->get(['id', 'captured_at']);

        $recommendationDates = $products
            ->filter(fn (Product $p): bool => $p->recommended_price !== null
                && $p->recommendation_updated_at !== null
                && $p->recommendation_updated_at->gte($from))
            ->map(fn (Product $p) => $p->recommendation_updated_at);

        $priceActions = AppliedPrice::query()
            ->whereBelongsTo($shop)
            ->where('applied_at', '>=', $from)
            ->count();

        $gaps = $products
            ->map(function (Product $p): ?float {
                /** @var RivalSnapshot|null $latest */
                $latest = $p->rivalSnapshots->first();
                if ($latest === null || $latest->isStale()) {
                    return null;
                }
                $cheapest = (float) $latest->cheapest_price;
                if ($cheapest <= 0) {
                    return null;
                }

                return ((float) $p->price - $cheapest) / $cheapest * 100;
            })
            ->filter(fn (?float $gap): bool => $gap !== null);

        $chart = [];
        for ($week = 0; $week < self::WEEKS; $week++) {
            $start = $from->copy()->addDays($week * 7);
            $end = $start->copy()->addDays(7);
            $inWeek = fn ($date): bool => $date !== null && $date->gte($start) && $date->lt($end);

            $chart[] = [
                'label' => 'هفته '.($week + 1),
                'recommends' => $recommendationDates->filter($inWeek)->count(),
                'rivalHits' => $snapshots->filter(fn (RivalSnapshot $s): bool => $inWeek($s->captured_at))->count(),
            ];
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'monthLabel' => '۴ هفته اخیر',
                'recommendationsIssued' => $recommendationDates->count(),
                'rivalChecks' => $snapshots->count(),
                'priceActions' => $priceActions,
                'avgVsRivalPct' => $gaps->isEmpty() ? 0 : round($gaps->avg(), 1),
                'chart' => $chart,
            ],
        ]);
    }
}
