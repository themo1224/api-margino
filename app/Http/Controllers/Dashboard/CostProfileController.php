<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\EnsureUserShop;
use App\Actions\EvaluateShopAlerts;
use App\Actions\RecomputeShopRecommendations;
use App\Http\Controllers\Controller;
use App\Models\ShopCostProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CostProfileController extends Controller
{
    public function edit(Request $request, EnsureUserShop $ensureUserShop): Response
    {
        $shop = $ensureUserShop->handle($request->user());
        $profile = $shop->costProfile;

        return Inertia::render('dashboard/costs', [
            'profile' => $profile === null ? null : [
                'staff_cost' => $profile->staff_cost,
                'rent_cost' => $profile->rent_cost,
                'utilities_cost' => $profile->utilities_cost,
                'other_overhead' => $profile->other_overhead,
                'min_margin_percent' => $profile->min_margin_percent,
                'allocation_method' => $profile->allocation_method,
            ],
            'hasProfile' => $profile !== null,
        ]);
    }

    public function update(
        Request $request,
        EnsureUserShop $ensureUserShop,
        RecomputeShopRecommendations $recompute,
        EvaluateShopAlerts $evaluateAlerts,
    ): RedirectResponse {
        $validated = $request->validate([
            'staff_cost' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'rent_cost' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'utilities_cost' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'other_overhead' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'min_margin_percent' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
        ]);

        $shop = $ensureUserShop->handle($request->user());

        DB::transaction(function () use ($shop, $validated, $recompute): void {
            ShopCostProfile::query()->updateOrCreate(
                ['shop_id' => $shop->id],
                [
                    ...$validated,
                    'allocation_method' => 'equal_split',
                ],
            );

            $recompute->handle($shop->fresh(['costProfile']));
        });

        $evaluateAlerts->handle($shop->fresh(['costProfile', 'user', 'products']));

        return redirect()
            ->route('dashboard.costs')
            ->with('status', 'cost_profile_saved');
    }
}
