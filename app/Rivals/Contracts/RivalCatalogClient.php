<?php

namespace App\Rivals\Contracts;

use App\Enums\RivalSource;
use App\Rivals\Data\RivalPriceReport;
use App\Rivals\Data\RivalSearchCandidate;

interface RivalCatalogClient
{
    public function source(): RivalSource;

    /**
     * @return list<RivalSearchCandidate>
     */
    public function search(string $query): array;

    public function fetchPrices(string $listingIdentity, ?string $searchId = null): ?RivalPriceReport;
}
