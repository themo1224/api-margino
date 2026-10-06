<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\BuildShopReport;
use App\Actions\EnsureUserShop;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, EnsureUserShop $ensureUserShop, BuildShopReport $buildReport): Response
    {
        $days = (int) $request->integer('days', 30);
        if (! in_array($days, [7, 30], true)) {
            $days = 30;
        }

        $shop = $ensureUserShop->handle($request->user());

        return Inertia::render('dashboard/reports', [
            'report' => $buildReport->handle($shop, $days),
        ]);
    }
}
