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
            'brand' => null,
            'barcode' => null,
            'price' => $price,
            'currency' => 'IRR',
            'recommended_price' => $price,
            'below_floor' => false,
            'floor_price' => null,
            'recommendation_updated_at' => now(),
            'last_synced_at' => now(),
            'last_applied_price' => null,
            'direct_cost' => null,
            'min_margin_percent' => null,
            'max_price' => null,
            'rivals_stale' => true,
            'cannot_match_profitably' => false,
        ];
    }

    /**
     * Set a direct COGS for engine tests.
     */
    public function withDirectCost(string $cost = '100000'): static
    {
        return $this->state(fn (array $attributes): array => [
            'direct_cost' => $cost,
        ]);
    }

    /**
     * Fixture for WP 1.8 floor-refuse tests (store price below a known floor).
     */
    public function belowFloor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => '1000000',
            'below_floor' => true,
            'floor_price' => '2000000',
            'recommended_price' => '2000000',
        ]);
    }
}
