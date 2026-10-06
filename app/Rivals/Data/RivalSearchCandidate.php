<?php

namespace App\Rivals\Data;

final readonly class RivalSearchCandidate
{
    public function __construct(
        public string $listingIdentity,
        public string $title,
        public ?string $price = null,
        public ?string $url = null,
        public ?string $imageUrl = null,
        public ?string $searchId = null,
    ) {}
}
