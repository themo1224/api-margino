<?php

namespace Database\Factories;

use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use App\Models\Product;
use App\Models\ProductRivalMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRivalMatch>
 */
class ProductRivalMatchFactory extends Factory
{
    protected $model = ProductRivalMatch::class;

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
            'listing_url' => 'https://example.test/'.fake()->uuid(),
            'search_query' => fake()->words(2, true),
            'confidence' => '0.9000',
            'status' => RivalMatchStatus::AutoLinked,
            'confirmed_at' => now(),
        ];
    }

    public function needsConfirm(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RivalMatchStatus::NeedsConfirm,
            'confidence' => '0.6000',
            'confirmed_at' => null,
        ]);
    }
}
