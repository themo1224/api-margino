<?php

namespace Database\Seeders;

use App\Actions\DiscoverProductRivals;
use App\Actions\HashApiKey;
use App\Actions\RecomputeShopRecommendations;
use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCostProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local seller login + shop + costs + sample products + fake rivals.
 *
 * Login: seller@pricing.test / password
 * API key: same as CONNECTOR_DEV_API_KEY (default below)
 */
class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        config([
            'rivals.driver' => 'fake',
            'rivals.torob_enabled' => false,
        ]);

        $plan = Plan::query()->firstOrCreate(
            ['code' => 'plan_starter'],
            [
                'label' => 'Starter',
                'status' => PlanStatus::Active,
                'rival_refresh_hours' => 24,
                'max_rival_products' => 50,
            ],
        );

        Plan::query()->firstOrCreate(
            ['code' => 'plan_pro'],
            [
                'label' => 'Pro',
                'status' => PlanStatus::Active,
                'rival_refresh_hours' => 6,
                'max_rival_products' => 500,
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'seller@pricing.test'],
            [
                'name' => 'Seller Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $shop = Shop::query()->updateOrCreate(
            ['public_id' => 'shop_local_dev'],
            [
                'user_id' => $user->id,
                'name' => 'Local Dev Store',
                'status' => ShopStatus::Active,
                'plan_id' => $plan->id,
            ],
        );

        // Ensure link even if shop already existed without user_id.
        if ($shop->user_id !== $user->id) {
            $shop->fill(['user_id' => $user->id])->save();
        }

        $plainKey = (string) config('connector.dev_api_key');
        $hasher = app(HashApiKey::class);

        ApiKey::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'prefix' => $hasher->prefix($plainKey),
            ],
            [
                'name' => 'Local development key',
                'key_hash' => $hasher->hash($plainKey),
                'revoked_at' => null,
            ],
        );

        ShopCostProfile::query()->updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'staff_cost' => '0',
                'rent_cost' => '0',
                'utilities_cost' => '0',
                'other_overhead' => '0',
                'min_margin_percent' => '20',
                'allocation_method' => 'equal_split',
            ],
        );

        $samples = [
            [
                'external_id' => '101',
                'sku' => 'PERF-101',
                'name' => 'عطر تست اکوا',
                'brand' => 'Acme',
                'price' => '2000000',
                'direct_cost' => '800000',
            ],
            [
                'external_id' => '102',
                'sku' => 'SILV-102',
                'name' => 'گردنبند نقره نمونه',
                'brand' => 'SilverCo',
                'price' => '1500000',
                'direct_cost' => '600000',
            ],
        ];

        $discover = app(DiscoverProductRivals::class);

        foreach ($samples as $row) {
            $product = Product::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'external_id' => $row['external_id'],
                ],
                [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'brand' => $row['brand'],
                    'price' => $row['price'],
                    'currency' => 'IRR',
                    'direct_cost' => $row['direct_cost'],
                    'last_synced_at' => now(),
                ],
            );

            $discover->handle($product->fresh(['shop.plan']));
        }

        app(RecomputeShopRecommendations::class)->handle($shop->fresh(['costProfile', 'plan', 'products']));

        $this->command?->info('Dashboard ready.');
        $this->command?->info('  Login:  seller@pricing.test / password');
        $this->command?->info('  Shop:   shop_local_dev');
        $this->command?->info('  API:    '.$plainKey);
        $this->command?->info('  Rivals: fake driver — open /dashboard/products');
    }
}
