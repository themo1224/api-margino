<?php

namespace App\Rivals\Fake;

use App\Enums\RivalSource;
use App\Rivals\Contracts\RivalCatalogClient;
use App\Rivals\Data\RivalPriceReport;
use App\Rivals\Data\RivalSearchCandidate;

/**
 * Deterministic rival catalog for tests and local without network.
 */
class FakeRivalCatalogClient implements RivalCatalogClient
{
    public function __construct(
        private readonly RivalSource $source = RivalSource::Torob,
    ) {}

    public function source(): RivalSource
    {
        return $this->source;
    }

    public function search(string $query): array
    {
        $slug = $this->slug($query);

        return [
            new RivalSearchCandidate(
                listingIdentity: 'fake-'.$this->source->value.'-'.$slug,
                title: trim($query),
                price: '1100000',
                url: 'https://example.test/rivals/'.$slug,
                searchId: '1',
            ),
            new RivalSearchCandidate(
                listingIdentity: 'fake-'.$this->source->value.'-other-'.$slug,
                title: 'محصول نامرتبط '.$slug,
                price: '900000',
                url: 'https://example.test/rivals/other-'.$slug,
                searchId: '2',
            ),
        ];
    }

    public function fetchPrices(string $listingIdentity, ?string $searchId = null): ?RivalPriceReport
    {
        return new RivalPriceReport(
            listingIdentity: $listingIdentity,
            cheapestPrice: '1100000',
            medianPrice: '1250000',
            competitorCount: 4,
            listingTitle: 'Fake listing '.$listingIdentity,
            currency: 'IRR',
        );
    }

    private function slug(string $query): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\x{0600}-\x{06FF}]+/u', '-', $query) ?? 'item');

        return trim($slug, '-') ?: 'item';
    }
}
