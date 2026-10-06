<?php

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Enums\PricingStrategy;
use App\Enums\ShopStatus;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
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
            'user_id' => null,
            'public_id' => 'shop_'.strtolower((string) Str::ulid()),
            'name' => fake()->company(),
            'status' => ShopStatus::Active,
            'plan_id' => Plan::factory(),
            'pricing_mode' => PricingMode::Alert,
            'alerts_enabled' => true,
            'rival_undercut_threshold_percent' => '5',
            'cost_stale_days' => 30,
            'pricing_strategy' => PricingStrategy::MatchCheapest,
            'undercut_percent' => '1',
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShopStatus::Inactive,
        ]);
    }
}
