<?php

namespace App\Rivals\Snapp;

use App\Enums\RivalSource;
use App\Rivals\Contracts\RivalCatalogClient;
use App\Rivals\Data\RivalPriceReport;

/**
 * Snapp comparison adapter stub — spike says no-go for production scrape yet.
 */
class SnappCatalogClient implements RivalCatalogClient
{
    public function source(): RivalSource
    {
        return RivalSource::Snapp;
    }

    public function search(string $query): array
    {
        return [];
    }

    public function fetchPrices(string $listingIdentity, ?string $searchId = null): ?RivalPriceReport
    {
        return null;
    }
}
