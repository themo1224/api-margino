<?php

namespace App\Actions;

use App\Enums\LicenseSource;
use App\Enums\LicenseStatus;
use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopLicense;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Map Zhaket/RTL (or manual) purchase → shop plan access.
 * Never grants forever unlock; ends_at required for marketplace sources.
 */
class ActivateMarketplaceLicense
{
    /**
     * @param  array{source: string, external_license_id: string, shop_public_id: string, plan_code?: string, ends_at?: string|null, starts_at?: string|null, meta?: array<string, mixed>|null}  $payload
     */
    public function handle(array $payload): ShopLicense
    {
        $source = LicenseSource::from($payload['source']);
        $planCode = $payload['plan_code'] ?? 'plan_starter';

        $shop = Shop::query()->where('public_id', $payload['shop_public_id'])->first();
        if ($shop === null) {
            throw ValidationException::withMessages([
                'shop_public_id' => 'Shop not found.',
            ]);
        }

        $plan = Plan::query()->where('code', $planCode)->where('status', PlanStatus::Active)->first();
        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'Unknown or inactive plan.',
            ]);
        }

        $endsAt = isset($payload['ends_at']) && filled($payload['ends_at'])
            ? Carbon::parse($payload['ends_at'])
            : null;

        if ($source !== LicenseSource::Manual && $endsAt === null) {
            throw ValidationException::withMessages([
                'ends_at' => 'Marketplace licenses must include an expiry (no forever unlock).',
            ]);
        }

        $startsAt = isset($payload['starts_at']) && filled($payload['starts_at'])
            ? Carbon::parse($payload['starts_at'])
            : now();

        $license = ShopLicense::query()->updateOrCreate(
            [
                'source' => $source,
                'external_license_id' => $payload['external_license_id'],
            ],
            [
                'shop_id' => $shop->id,
                'plan_code' => $plan->code,
                'status' => LicenseStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'meta' => $payload['meta'] ?? null,
            ],
        );

        $shop->fill([
            'plan_id' => $plan->id,
            'status' => ShopStatus::Active,
        ])->save();

        return $license->fresh(['shop.plan']);
    }
}
