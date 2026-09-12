<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => 'shop_'.strtolower((string) Str::ulid()),
            'name' => fake()->company(),
            'status' => ShopStatus::Active,
            'plan_id' => Plan::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShopStatus::Inactive,
        ]);
    }
}
