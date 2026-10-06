<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Actions\IssueApiKey;
use App\Actions\RevokeApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingApiKeysController extends Controller
{
    public function index(Request $request, EnsureUserShop $ensureUserShop): JsonResponse
    {
        $shop = $ensureUserShop->handle($request->user());

        $keys = ApiKey::query()
            ->whereBelongsTo($shop)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiKey $key): array => $this->toItem($key));

        return response()->json([
            'status' => 200,
            'data' => $keys,
        ]);
    }

    public function store(Request $request, EnsureUserShop $ensureUserShop, IssueApiKey $issueApiKey): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $shop = $ensureUserShop->handle($request->user());
        $result = $issueApiKey->handle($shop, $validated['name'] ?? null);

        return response()->json([
            'status' => 200,
            'data' => $this->toItem($result['api_key']),
            'secret' => $result['plain_key'],
        ], 201);
    }

    public function revoke(
        Request $request,
        int $id,
        EnsureUserShop $ensureUserShop,
        RevokeApiKey $revokeApiKey,
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());
        $revokeApiKey->handle($shop, $id);

        $keys = ApiKey::query()
            ->whereBelongsTo($shop)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiKey $key): array => $this->toItem($key));

        return response()->json([
            'status' => 200,
            'data' => $keys,
        ]);
    }

    /**
     * @return array{id: string, name: string, prefix: string, createdAt: string|null, lastUsedAt: null, revoked: bool}
     */
    private function toItem(ApiKey $key): array
    {
        return [
            'id' => (string) $key->id,
            'name' => (string) ($key->name ?? 'کلید اتصال'),
            'prefix' => $key->prefix,
            'createdAt' => $key->created_at?->toIso8601String(),
            'lastUsedAt' => null,
            'revoked' => $key->revoked_at !== null,
        ];
    }
}
