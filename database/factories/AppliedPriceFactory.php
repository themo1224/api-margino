<?php

namespace Database\Factories;

use App\Enums\AppliedSource;
use App\Models\AppliedPrice;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppliedPrice>
 */
class AppliedPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'applied_price' => '1450000',
            'currency' => 'IRR',
            'source' => AppliedSource::Manual,
            'applied_at' => now(),
        ];
    }
}
