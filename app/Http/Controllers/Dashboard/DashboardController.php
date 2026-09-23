<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EnsureUserShop $ensureUserShop): Response
    {
        $shop = $ensureUserShop->handle($request->user());
        $shop->load('plan');

        $hasActiveKey = ApiKey::query()
            ->whereBelongsTo($shop)
            ->whereNull('revoked_at')
            ->exists();

        $productCount = $shop->products()->count();
        $hasCostProfile = $shop->costProfile()->exists();

        return Inertia::render('dashboard/index', [
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
            ],
            'plan' => [
                'code' => $shop->plan->code,
                'label' => $shop->plan->label,
                'status' => $shop->plan->status->value,
            ],
            'empty' => [
                'no_key' => ! $hasActiveKey,
                'no_products' => $productCount === 0,
                'no_cost' => ! $hasCostProfile,
            ],
            'stats' => [
                'product_count' => $productCount,
                'has_active_key' => $hasActiveKey,
                'has_cost_profile' => $hasCostProfile,
            ],
        ]);
    }
}
