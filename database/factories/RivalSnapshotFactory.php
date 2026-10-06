<?php

namespace Database\Factories;

use App\Enums\RivalSource;
use App\Models\Product;
use App\Models\RivalSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RivalSnapshot>
 */
class RivalSnapshotFactory extends Factory
{
    protected $model = RivalSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'source' => RivalSource::Torob,
            'listing_identity' => 'listing_'.fake()->unique()->numerify('######'),
            'listing_title' => fake()->words(4, true),
            'cheapest_price' => (string) fake()->numberBetween(100000, 5000000),
            'median_price' => (string) fake()->numberBetween(100000, 5000000),
            'competitor_count' => fake()->numberBetween(1, 12),
            'currency' => 'IRR',
            'captured_at' => now(),
        ];
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'captured_at' => now()->subDays(3),
        ]);
    }
}
