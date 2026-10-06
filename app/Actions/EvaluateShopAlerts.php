<?php

namespace App\Actions;

use App\Enums\AlertType;
use App\Enums\PricingMode;
use App\Mail\BelowFloorAlertMail;
use App\Mail\CostProfileStaleAlertMail;
use App\Mail\RivalUndercutAlertMail;
use App\Models\Product;
use App\Models\RivalSnapshot;
use App\Models\Shop;
use App\Models\ShopAlertDispatch;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class EvaluateShopAlerts
{
    public function handle(Shop $shop): void
    {
        if (! (bool) config('alerts.enabled')) {
            return;
        }

        $shop->loadMissing(['user', 'costProfile', 'products.rivalSnapshots']);

        if (! $shop->alerts_enabled) {
            return;
        }

        // Full Starter is alert-mode only; auto-apply comes later.
        if ($shop->pricing_mode === PricingMode::Auto) {
            // still send alerts so sellers notice floor/rival issues
        }

        $email = $shop->user?->email;
        if (! filled($email)) {
            return;
        }

        foreach ($shop->products as $product) {
            if ($product->below_floor && $shop->wantsAlert(AlertType::BelowFloor)) {
                $this->sendOnce(
                    shop: $shop,
                    product: $product,
                    type: AlertType::BelowFloor,
                    dedupeKey: 'below_floor:'.$product->id.':'.($product->floor_price ?? ''),
                    mail: new BelowFloorAlertMail($shop, $product),
                    to: $email,
                );
            }

            $snapshot = $product->rivalSnapshots
                ->sortByDesc(fn (RivalSnapshot $s) => $s->captured_at?->timestamp ?? 0)
                ->first();

            if (
                $snapshot !== null
                && $shop->wantsAlert(AlertType::RivalUndercut)
                && ! $snapshot->isStale()
                && $this->isRivalUndercut($shop, $product, $snapshot)
            ) {
                $this->sendOnce(
                    shop: $shop,
                    product: $product,
                    type: AlertType::RivalUndercut,
                    dedupeKey: 'rival_undercut:'.$product->id.':'.$snapshot->cheapest_price,
                    mail: new RivalUndercutAlertMail($shop, $product, $snapshot),
                    to: $email,
                );
            }
        }

        if ($shop->wantsAlert(AlertType::StaleCost) && $this->isCostStale($shop)) {
            $days = (int) ($shop->cost_stale_days ?: config('alerts.default_cost_stale_days'));
            $this->sendOnce(
                shop: $shop,
                product: null,
                type: AlertType::StaleCost,
                dedupeKey: 'stale_cost:'.$shop->id.':'.($shop->costProfile?->updated_at?->toDateString() ?? 'none'),
                mail: new CostProfileStaleAlertMail($shop, $days),
                to: $email,
            );
        }
    }

    private function isRivalUndercut(Shop $shop, Product $product, RivalSnapshot $snapshot): bool
    {
        $threshold = Decimal::normalize(
            (string) ($shop->rival_undercut_threshold_percent
                ?: config('alerts.default_undercut_threshold_percent'))
        );

        $seller = Decimal::normalize($product->price);
        $cheapest = Decimal::normalize($snapshot->cheapest_price);

        if (Decimal::compare($seller, '0') <= 0) {
            return false;
        }

        if (Decimal::compare($cheapest, $seller) >= 0) {
            return false;
        }

        $dropPct = Decimal::mul(Decimal::div(Decimal::sub($seller, $cheapest), $seller), '100');

        return Decimal::compare($dropPct, $threshold) >= 0;
    }

    private function isCostStale(Shop $shop): bool
    {
        $profile = $shop->costProfile;
        if ($profile === null || $profile->updated_at === null) {
            return false;
        }

        $days = (int) ($shop->cost_stale_days ?: config('alerts.default_cost_stale_days'));

        return $days > 0 && $profile->updated_at->lte(Carbon::now()->subDays($days));
    }

    private function sendOnce(
        Shop $shop,
        ?Product $product,
        AlertType $type,
        string $dedupeKey,
        object $mail,
        string $to,
    ): void {
        $dedupeHours = (int) config('alerts.dedupe_hours', 24);
        $recent = ShopAlertDispatch::query()
            ->where('shop_id', $shop->id)
            ->where('dedupe_key', $dedupeKey)
            ->where('sent_at', '>=', Carbon::now()->subHours($dedupeHours))
            ->exists();

        if ($recent) {
            return;
        }

        Mail::to($to)->send($mail);

        ShopAlertDispatch::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'dedupe_key' => $dedupeKey,
            ],
            [
                'product_id' => $product?->id,
                'alert_type' => $type,
                'sent_at' => Carbon::now(),
            ],
        );
    }
}
