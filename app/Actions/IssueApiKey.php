<?php

namespace App\Actions;

use App\Models\ApiKey;
use App\Models\Shop;
use Illuminate\Support\Str;

class IssueApiKey
{
    public function __construct(
        private readonly HashApiKey $hasher,
    ) {}

    /**
     * Create a new API key for the shop. Returns the plaintext once; only the hash is stored.
     *
     * @return array{api_key: ApiKey, plain_key: string}
     */
    public function handle(Shop $shop, ?string $name = null): array
    {
        $plainKey = 'pk_'.Str::random(40);

        $apiKey = ApiKey::query()->create([
            'shop_id' => $shop->id,
            'prefix' => $this->hasher->prefix($plainKey),
            'key_hash' => $this->hasher->hash($plainKey),
            'name' => $name ?: 'کلید اتصال',
            'revoked_at' => null,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_key' => $plainKey,
        ];
    }
}
