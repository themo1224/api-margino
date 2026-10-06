<?php

use App\Jobs\DispatchDueRivalRefreshesJob;
use App\Jobs\EvaluateAllShopAlertsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rival refresh + alert evaluation within ~1 hour of relevant change.
Schedule::job(new DispatchDueRivalRefreshesJob)->hourly();
Schedule::job(new EvaluateAllShopAlertsJob)->hourly();
