<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = (string) fake()->numberBetween(10000, 9000000);

        return [
            'shop_id' => Shop::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1, 999999),
            'sku' => fake()->optional()->bothify('SKU-###'),
            'name' => fake()->words(3, true),
            'price' => $price,
            'currency' => 'IRR',
            'recommended_price' => $price,
            'below_floor' => false,
            'floor_price' => null,
            'recommendation_updated_at' => now(),
            'last_synced_at' => now(),
            'last_applied_price' => null,
        ];
    }

    /**
     * Fixture for WP 1.8 floor-refuse tests before the B6 engine exists.
     */
    public function belowFloor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'below_floor' => true,
            'floor_price' => '2000000',
            'recommended_price' => '2000000',
        ]);
    }
}
