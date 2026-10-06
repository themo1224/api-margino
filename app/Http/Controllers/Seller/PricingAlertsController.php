<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Enums\AlertType;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingAlertsController extends Controller
{
    public function show(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());

        return $this->respond($shop);
    }

    public function update(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $validated = $request->validate([
            'belowCost' => ['required', 'boolean'],
            'aboveRivals' => ['required', 'boolean'],
            'staleCost' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
        ]);

        $shop = $ensureUserShop->handle($request->user());

        $shop->update([
            'alerts_enabled' => (bool) $validated['email'],
            'alert_prefs' => [
                AlertType::BelowFloor->value => (bool) $validated['belowCost'],
                AlertType::RivalUndercut->value => (bool) $validated['aboveRivals'],
                AlertType::StaleCost->value => (bool) $validated['staleCost'],
            ],
        ]);

        return $this->respond($shop->refresh());
    }

    private function respond(Shop $shop): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'belowCost' => $shop->wantsAlert(AlertType::BelowFloor),
                'aboveRivals' => $shop->wantsAlert(AlertType::RivalUndercut),
                'staleCost' => $shop->wantsAlert(AlertType::StaleCost),
                'email' => (bool) $shop->alerts_enabled,
                'sms' => false,
            ],
        ]);
    }
}
