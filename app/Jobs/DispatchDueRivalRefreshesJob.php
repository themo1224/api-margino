<?php

namespace App\Jobs;

use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDueRivalRefreshesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Shop::query()
            ->with('plan')
            ->orderBy('id')
            ->each(function (Shop $shop): void {
                RefreshShopRivalSnapshotsJob::dispatch($shop->id);
            });
    }
}
