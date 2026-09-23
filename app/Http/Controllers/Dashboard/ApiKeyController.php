<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\EnsureUserShop;
use App\Actions\IssueApiKey;
use App\Actions\RevokeApiKey;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request, EnsureUserShop $ensureUserShop): Response
    {
        $shop = $ensureUserShop->handle($request->user());

        $keys = ApiKey::query()
            ->whereBelongsTo($shop)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiKey $key): array => [
                'id' => $key->id,
                'name' => $key->name,
                'prefix' => $key->prefix,
                'revoked_at' => $key->revoked_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
                'is_active' => $key->revoked_at === null,
            ]);

        return Inertia::render('dashboard/api-keys', [
            'keys' => $keys,
            'revealedKey' => $request->session()->get('revealed_api_key'),
        ]);
    }

    public function store(Request $request, EnsureUserShop $ensureUserShop, IssueApiKey $issueApiKey): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $shop = $ensureUserShop->handle($request->user());

        $result = $issueApiKey->handle($shop, $validated['name'] ?? null);

        return redirect()
            ->route('dashboard.api-keys')
            ->with('revealed_api_key', $result['plain_key']);
    }

    public function destroy(
        Request $request,
        int $apiKey,
        EnsureUserShop $ensureUserShop,
        RevokeApiKey $revokeApiKey,
    ): RedirectResponse {
        $shop = $ensureUserShop->handle($request->user());

        $revokeApiKey->handle($shop, $apiKey);

        return redirect()
            ->route('dashboard.api-keys')
            ->with('status', 'api_key_revoked');
    }
}
