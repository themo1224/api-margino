<?php

namespace Database\Factories;

use App\Actions\HashApiKey;
use App\Models\ApiKey;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasher = app(HashApiKey::class);
        $plainKey = 'test_'.Str::random(40);

        return [
            'shop_id' => Shop::factory(),
            'prefix' => $hasher->prefix($plainKey),
            'key_hash' => $hasher->hash($plainKey),
            'name' => 'Test key',
            'revoked_at' => null,
        ];
    }

    public function plaintext(string $plainKey): static
    {
        return $this->state(function (array $attributes) use ($plainKey): array {
            $hasher = app(HashApiKey::class);

            return [
                'prefix' => $hasher->prefix($plainKey),
                'key_hash' => $hasher->hash($plainKey),
            ];
        });
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
