<?php

namespace Database\Factories;

use App\Enums\LicenseSource;
use App\Enums\LicenseStatus;
use App\Models\Shop;
use App\Models\ShopLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopLicense>
 */
class ShopLicenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'source' => LicenseSource::Zhaket,
            'external_license_id' => 'zhk_'.fake()->unique()->numerify('########'),
            'plan_code' => 'plan_starter',
            'status' => LicenseStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(1),
            'meta' => null,
        ];
    }
}
