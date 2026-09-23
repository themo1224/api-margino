<?php

namespace App\Actions;

use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnsureUserShop
{
    /**
     * Return the user's shop, creating a Starter shop if missing (v1: one shop per user).
     */
    public function handle(User $user, ?string $shopName = null): Shop
    {
        $existing = $user->shop;

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $shopName): Shop {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->load('shop');

            if ($locked->shop !== null) {
                return $locked->shop;
            }

            $plan = Plan::query()->firstOrCreate(
                ['code' => 'plan_starter'],
                [
                    'label' => 'Starter',
                    'status' => PlanStatus::Active,
                ],
            );

            return Shop::query()->create([
                'user_id' => $locked->id,
                'name' => filled($shopName) ? $shopName : 'فروشگاه '.$locked->name,
                'status' => ShopStatus::Active,
                'plan_id' => $plan->id,
            ]);
        });
    }
}
