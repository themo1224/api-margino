<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopCostProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCostProfile>
 */
class ShopCostProfileFactory extends Factory
{
    protected $model = ShopCostProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'staff_cost' => '0',
            'rent_cost' => '0',
            'utilities_cost' => '0',
            'other_overhead' => '0',
            'min_margin_percent' => '0',
            'allocation_method' => 'equal_split',
        ];
    }
}
