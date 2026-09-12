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
        ];
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
