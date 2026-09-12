<?php

namespace Database\Seeders;

use App\Actions\HashApiKey;
use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ConnectorSeeder extends Seeder
{
    /**
     * Seed one active Starter shop and a known local API key.
     *
     * Local smoke (after `php artisan db:seed`):
     *
     *   Base URL: http://localhost:8000/v1
     *   Auth:     Authorization: Bearer <CONNECTOR_DEV_API_KEY>
     *
     *   curl -X POST http://localhost:8000/v1/connector/validate \
     *     -H "Authorization: Bearer $CONNECTOR_DEV_API_KEY" \
     *     -H "Content-Type: application/json" \
     *     -d "{}"
     *
     *   curl -X POST http://localhost:8000/v1/connector/products/sync \
     *     -H "Authorization: Bearer $CONNECTOR_DEV_API_KEY" \
     *     -H "Content-Type: application/json" \
     *     -d '{"currency":"IRR","products":[{"external_id":"42","sku":"SKU-001","name":"Sample Product","price":"1500000"}]}'
     *
     *   curl http://localhost:8000/v1/connector/products/42/recommendation \
     *     -H "Authorization: Bearer $CONNECTOR_DEV_API_KEY"
     *
     *   curl -X POST http://localhost:8000/v1/connector/products/42/applied \
     *     -H "Authorization: Bearer $CONNECTOR_DEV_API_KEY" \
     *     -H "Content-Type: application/json" \
     *     -d '{"applied_price":"1500000","currency":"IRR","source":"manual","applied_at":"2026-09-07T12:05:00Z"}'
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $plainKey = (string) config('connector.dev_api_key');
        $hasher = app(HashApiKey::class);

        $plan = Plan::query()->firstOrCreate(
            ['code' => 'plan_starter'],
            [
                'label' => 'Starter',
                'status' => PlanStatus::Active,
            ],
        );

        $shop = Shop::query()->firstOrCreate(
            ['public_id' => 'shop_local_dev'],
            [
                'name' => 'Local Dev Store',
                'status' => ShopStatus::Active,
                'plan_id' => $plan->id,
            ],
        );

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
    }
}
