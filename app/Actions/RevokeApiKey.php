<?php

namespace App\Actions;

use App\Models\ApiKey;
use App\Models\Shop;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RevokeApiKey
{
    public function handle(Shop $shop, int $apiKeyId): ApiKey
    {
        $apiKey = ApiKey::query()
            ->whereBelongsTo($shop)
            ->whereKey($apiKeyId)
            ->first();

        if ($apiKey === null) {
            throw (new ModelNotFoundException)->setModel(ApiKey::class, [$apiKeyId]);
        }

        if ($apiKey->revoked_at === null) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
        }

        return $apiKey->refresh();
    }
}
