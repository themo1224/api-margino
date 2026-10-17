<?php

namespace App\Console\Commands;

use App\Jobs\ProcessShopAutoApplyJob;
use App\Models\Shop;
use Illuminate\Console\Command;

class RunAutoApplyCycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pricing:auto-apply-cycle {--shop= : Specific shop ID to run for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executes safe automatic price application for stores on Pro/Business plans with auto-apply enabled';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shopId = $this->option('shop');

        $query = Shop::query()
            ->where('auto_apply_enabled', true)
            ->with(['plan', 'licenses']);

        if ($shopId) {
            $query->where('id', $shopId);
        }

        $shops = $query->get();
        $this->info("Found {$shops->count()} shops with auto-apply enabled.");

        $processed = 0;
        foreach ($shops as $shop) {
            if (! $shop->isAutoApplyAllowed()) {
                $this->warn("Skipping shop #{$shop->id}: Plan {$shop->plan?->code} or active license not allowed for Auto-Apply.");
                continue;
            }

            $this->info("Processing auto-apply for shop #{$shop->id} ({$shop->name})...");
            ProcessShopAutoApplyJob::dispatch($shop);
            $processed++;
        }

        $this->info("Dispatched auto-apply jobs for {$processed} eligible shops.");

        return Command::SUCCESS;
    }
}
