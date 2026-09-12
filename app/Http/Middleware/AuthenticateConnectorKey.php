<?php

namespace App\Http\Middleware;

use App\Actions\HashApiKey;
use App\Enums\PlanStatus;
use App\Enums\ShopStatus;
use App\Exceptions\InactivePlanException;
use App\Exceptions\InvalidApiKeyException;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateConnectorKey
{
    public function __construct(private HashApiKey $hasher) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->bearerToken();

        if (! is_string($plainKey) || $plainKey === '') {
            throw new InvalidApiKeyException;
        }

        $apiKey = ApiKey::query()
            ->with(['shop.plan'])
            ->where('prefix', $this->hasher->prefix($plainKey))
            ->whereNull('revoked_at')
            ->get()
            ->first(fn (ApiKey $candidate): bool => $this->hasher->equals($plainKey, $candidate->key_hash));

        if ($apiKey === null) {
            throw new InvalidApiKeyException;
        }

        $shop = $apiKey->shop;
        $plan = $shop->plan;

        if ($shop->status !== ShopStatus::Active || $plan->status !== PlanStatus::Active) {
            throw new InactivePlanException;
        }

        Context::addHidden('connector.shop', $shop);
        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
