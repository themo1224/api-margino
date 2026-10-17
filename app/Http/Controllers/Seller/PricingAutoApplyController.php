<?php

namespace App\Http\Controllers\Seller;

use App\Actions\EnsureUserShop;
use App\Actions\EvaluateAutoApplySafety;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessShopAutoApplyJob;
use App\Models\Product;
use App\Models\Shop;
use App\Support\SellerProductRivalMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingAutoApplyController extends Controller
{
    public function show(
        Request $request,
        EnsureUserShop $ensureUserShop,
        EvaluateAutoApplySafety $evaluator
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());
        $shop->loadMissing(['plan', 'licenses']);

        return response()->json([
            'status' => 200,
            'data' => $this->formatSettings($shop, $evaluator),
        ]);
    }

    public function update(
        Request $request,
        EnsureUserShop $ensureUserShop,
        EvaluateAutoApplySafety $evaluator
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());
        $shop->loadMissing(['plan', 'licenses']);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'maxDropPercent' => ['required', 'numeric', 'min:1', 'max:50'],
            'onlyFreshRivals' => ['sometimes', 'boolean'],
            'notifyDaily' => ['sometimes', 'boolean'],
        ]);

        if ($validated['enabled'] && ! $shop->isAutoApplyAllowed()) {
            return response()->json([
                'status' => 403,
                'code' => 'plan_upgrade_required',
                'message' => 'قابلیت اعمال خودکار مخصوص پلن‌های حرفه‌ای و سازمانی است.',
            ], 403);
        }

        $shop->update([
            'auto_apply_enabled' => (bool) $validated['enabled'],
            'auto_apply_max_drop_percent' => (string) $validated['maxDropPercent'],
            'auto_apply_only_fresh_rivals' => isset($validated['onlyFreshRivals']) ? (bool) $validated['onlyFreshRivals'] : true,
            'auto_apply_notify_daily' => isset($validated['notifyDaily']) ? (bool) $validated['notifyDaily'] : true,
        ]);

        return response()->json([
            'status' => 200,
            'data' => $this->formatSettings($shop->refresh(), $evaluator),
        ]);
    }

    public function toggleProduct(
        Product $product,
        Request $request,
        EnsureUserShop $ensureUserShop
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());

        if ($product->shop_id !== $shop->id) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found',
            ], 404);
        }

        $validated = $request->validate([
            'excluded' => ['required', 'boolean'],
        ]);

        $product->update([
            'auto_apply_excluded' => (bool) $validated['excluded'],
        ]);

        $product->loadMissing([
            'rivalMatches',
            'rivalSnapshots' => fn ($q) => $q->orderByDesc('captured_at'),
        ]);

        return response()->json([
            'status' => 200,
            'data' => SellerProductRivalMapper::toArray($product),
        ]);
    }

    public function runNow(
        Request $request,
        EnsureUserShop $ensureUserShop
    ): JsonResponse {
        $shop = $ensureUserShop->handle($request->user());
        $shop->loadMissing(['plan', 'licenses']);

        if (! $shop->isAutoApplyAllowed()) {
            return response()->json([
                'status' => 403,
                'code' => 'plan_upgrade_required',
                'message' => 'قابلیت اعمال خودکار مخصوص پلن‌های حرفه‌ای و سازمانی است.',
            ], 403);
        }

        $job = new ProcessShopAutoApplyJob($shop);
        $result = app()->call([$job, 'handle']);

        return response()->json([
            'status' => 200,
            'data' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettings(Shop $shop, EvaluateAutoApplySafety $evaluator): array
    {
        $products = Product::query()
            ->whereBelongsTo($shop)
            ->get();

        $totalCount = $products->count();
        $excludedCount = $products->where('auto_apply_excluded', true)->count();

        $eligibleCount = 0;
        foreach ($products as $prod) {
            $eval = $evaluator->handle($prod, $shop);
            if ($eval['eligible']) {
                $eligibleCount++;
            }
        }

        return [
            'enabled' => (bool) $shop->auto_apply_enabled,
            'maxDropPercent' => (float) $shop->auto_apply_max_drop_percent,
            'onlyFreshRivals' => (bool) $shop->auto_apply_only_fresh_rivals,
            'notifyDaily' => (bool) $shop->auto_apply_notify_daily,
            'lastRunAt' => $shop->last_auto_apply_at?->toIso8601String(),
            'planAllowed' => $shop->isAutoApplyAllowed(),
            'planCode' => $shop->plan?->code ?? 'starter',
            'eligibleProductsCount' => $eligibleCount,
            'excludedProductsCount' => $excludedCount,
            'totalProductsCount' => $totalCount,
        ];
    }
}
