<?php

namespace App\Jobs;

use App\Actions\EvaluateShopAlerts;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAllShopAlertsJob implements ShouldQueue
{
    use Queueable;

    public function handle(EvaluateShopAlerts $evaluateAlerts): void
    {
        Shop::query()
            ->where('alerts_enabled', true)
            ->orderBy('id')
            ->each(function (Shop $shop) use ($evaluateAlerts): void {
                $evaluateAlerts->handle($shop);
            });
    }
}
