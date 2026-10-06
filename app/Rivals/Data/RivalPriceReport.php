<?php

namespace App\Rivals\Data;

final readonly class RivalPriceReport
{
    public function __construct(
        public string $listingIdentity,
        public string $cheapestPrice,
        public ?string $medianPrice,
        public int $competitorCount,
        public ?string $listingTitle = null,
        public string $currency = 'IRR',
    ) {}
}
