<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\ActivateMarketplaceLicense;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceLicenseController extends Controller
{
    public function __invoke(Request $request, ActivateMarketplaceLicense $activate): JsonResponse
    {
        $secret = (string) config('marketplace.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Marketplace-Secret', ''))) {
            return response()->json([
                'error' => [
                    'code' => 'unauthorized',
                    'message' => 'Invalid marketplace webhook secret.',
                ],
            ], 401);
        }

        $data = $request->validate([
            'source' => ['required', 'in:zhaket,rtl,manual'],
            'external_license_id' => ['required', 'string', 'max:191'],
            'shop_public_id' => ['required', 'string', 'max:64'],
            'plan_code' => ['sometimes', 'string', 'max:64'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $license = $activate->handle($data);

        return response()->json([
            'ok' => true,
            'license' => [
                'id' => $license->id,
                'source' => $license->source->value,
                'external_license_id' => $license->external_license_id,
                'plan_code' => $license->plan_code,
                'status' => $license->status->value,
                'ends_at' => $license->ends_at?->toIso8601String(),
            ],
            'shop' => [
                'id' => $license->shop->public_id,
                'plan' => $license->shop->plan->code,
            ],
        ]);
    }
}
