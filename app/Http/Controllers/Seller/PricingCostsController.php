<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Actions\RecomputeShopRecommendations;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingCostsController extends Controller
{
    public function show(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());
        $profile = $shop->costProfile;

        return response()->json([
            'status' => 200,
            'data' => [
                'supplierPct' => 0,
                'dollarPct' => 0,
                'rentMonthly' => $profile ? (int) round((float) $profile->rent_cost) : 0,
                'staffMonthly' => $profile ? (int) round((float) $profile->staff_cost) : 0,
                'otherMonthly' => $profile ? (int) round((float) $profile->other_overhead + (float) $profile->utilities_cost) : 0,
                'targetMarginPct' => $profile ? (float) $profile->min_margin_percent : 15.0,
                'updatedAt' => $profile?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(
        Request $request,
        EnsureUserShop $ensureUserShop,
        RecomputeShopRecommendations $recompute,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        $validated = $request->validate([
            'rentMonthly' => ['nullable', 'numeric', 'min:0'],
            'staffMonthly' => ['nullable', 'numeric', 'min:0'],
            'otherMonthly' => ['nullable', 'numeric', 'min:0'],
            'targetMarginPct' => ['nullable', 'numeric', 'min:0'],
            'supplierPct' => ['nullable', 'numeric', 'min:0'],
            'dollarPct' => ['nullable', 'numeric', 'min:0'],
        ]);

        $profile = $shop->costProfile()->updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'rent_cost' => (string) ($validated['rentMonthly'] ?? 0),
                'staff_cost' => (string) ($validated['staffMonthly'] ?? 0),
                'other_overhead' => (string) ($validated['otherMonthly'] ?? 0),
                'utilities_cost' => '0',
                'min_margin_percent' => (string) ($validated['targetMarginPct'] ?? 15),
                'allocation_method' => 'equal_split',
            ]
        );

        $recompute->handle($shop->fresh(['costProfile', 'plan']));

        return response()->json([
            'status' => 200,
            'data' => [
                'supplierPct' => (float) ($validated['supplierPct'] ?? 0),
                'dollarPct' => (float) ($validated['dollarPct'] ?? 0),
                'rentMonthly' => (int) round((float) $profile->rent_cost),
                'staffMonthly' => (int) round((float) $profile->staff_cost),
                'otherMonthly' => (int) round((float) $profile->other_overhead),
                'targetMarginPct' => (float) $profile->min_margin_percent,
                'updatedAt' => $profile->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
