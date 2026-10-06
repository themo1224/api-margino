<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan_'.fake()->unique()->lexify('??????'),
            'label' => 'Starter',
            'status' => PlanStatus::Active,
            'max_rival_products' => 50,
            'rival_refresh_hours' => 24,
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'plan_starter',
            'label' => 'Starter',
            'max_rival_products' => 50,
            'rival_refresh_hours' => 24,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'plan_pro',
            'label' => 'Pro',
            'max_rival_products' => 500,
            'rival_refresh_hours' => 6,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlanStatus::Inactive,
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlanStatus::PastDue,
        ]);
    }
}
