<?php

namespace App\Actions;

class HashApiKey
{
    public function hash(string $plainKey): string
    {
        return hash_hmac('sha256', $plainKey, (string) config('app.key'));
    }

    public function prefix(string $plainKey): string
    {
        return substr($plainKey, 0, (int) config('connector.key_prefix_length'));
    }

    public function equals(string $plainKey, string $hash): bool
    {
        return hash_equals($hash, $this->hash($plainKey));
    }
}
