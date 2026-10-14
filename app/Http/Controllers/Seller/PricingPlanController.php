<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingPlanController extends Controller
{
    public function show(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());

        return response()->json([
            'status' => 200,
            'data' => $this->formatPlanUsage($shop),
        ]);
    }

    public function upgrade(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());

        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:starter,pro,business'],
            'billingCycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('code', $validated['tier'])->firstOrFail();

        $shop->update(['plan_id' => $plan->id]);
        $shop->refresh();

        return response()->json([
            'status' => 200,
            'data' => $this->formatPlanUsage($shop, $validated['billingCycle'] ?? 'monthly'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatPlanUsage(Shop $shop, string $billingCycle = 'monthly'): array
    {
        $shop->loadMissing(['plan', 'licenses']);
        $plan = $shop->plan;
        $code = $plan?->code ?? 'starter';

        $refreshInterval = 'daily';
        $refreshLabel = 'روزانه';
        if (($plan?->rival_refresh_hours ?? 24) <= 1) {
            $refreshInterval = 'priority';
            $refreshLabel = 'هر ساعت (اولویت بالا)';
        } elseif (($plan?->rival_refresh_hours ?? 24) <= 6) {
            $refreshInterval = 'hourly';
            $refreshLabel = 'هر ۶ ساعت';
        }

        $activeLicense = $shop->licenses
            ->first(fn ($lic) => $lic->isCurrentlyActive());

        $renewalDate = $activeLicense?->ends_at
            ? $activeLicense->ends_at->format('Y/m/d')
            : now()->addDays(28)->format('Y/m/d');

        $daysRemaining = $activeLicense?->ends_at
            ? max(0, (int) now()->diffInDays($activeLicense->ends_at, false))
            : 28;

        return [
            'tier' => $code,
            'tierLabel' => $plan?->label ?? 'طرح پایه',
            'productsUsed' => $shop->products()->count(),
            'productsLimit' => $plan?->max_rival_products ?? 50,
            'rivalRefreshInterval' => $refreshInterval,
            'rivalRefreshLabel' => $refreshLabel,
            'autoApplyAllowed' => in_array($code, ['pro', 'business'], true),
            'smsAlertsAllowed' => $code === 'business',
            'renewalDate' => $renewalDate,
            'daysRemaining' => $daysRemaining,
            'billingCycle' => $billingCycle,
        ];
    }
}
