<?php

namespace App\Jobs;

use App\Actions\EvaluateAutoApplySafety;
use App\Actions\ExecuteAutoApply;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessShopAutoApplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Shop $shop
    ) {}

    /**
     * @return array{
     *     applied_count: int,
     *     skipped_count: int,
     *     applied_ids: array<int>,
     *     rejections: array<int, string>
     * }
     */
    public function handle(
        EvaluateAutoApplySafety $evaluator,
        ExecuteAutoApply $executor
    ): array {
        $this->shop->loadMissing(['plan', 'licenses', 'costProfile']);

        if (! $this->shop->isAutoApplyAllowed() || ! $this->shop->auto_apply_enabled) {
            return [
                'applied_count' => 0,
                'skipped_count' => 0,
                'applied_ids' => [],
                'rejections' => [],
            ];
        }

        $products = Product::query()
            ->whereBelongsTo($this->shop)
            ->where('auto_apply_excluded', false)
            ->whereNotNull('recommended_price')
            ->get();

        $appliedIds = [];
        $rejections = [];

        foreach ($products as $product) {
            $eval = $evaluator->handle($product, $this->shop);

            if ($eval['eligible'] && $eval['recommended_price'] !== null) {
                $executor->handle($product, $eval['recommended_price'], $this->shop);
                $appliedIds[] = $product->id;
            } else {
                if ($eval['reason'] !== null && $eval['reason'] !== 'price_unchanged') {
                    $rejections[$product->id] = $eval['reason'];
                }
            }
        }

        $this->shop->update([
            'last_auto_apply_at' => Carbon::now(),
        ]);

        Log::info('Auto-apply executed for shop', [
            'shop_id' => $this->shop->id,
            'applied' => count($appliedIds),
            'skipped' => count($rejections),
        ]);

        return [
            'applied_count' => count($appliedIds),
            'skipped_count' => count($rejections),
            'applied_ids' => $appliedIds,
            'rejections' => $rejections,
        ];
    }
}
