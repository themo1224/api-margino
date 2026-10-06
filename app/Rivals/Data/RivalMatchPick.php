<?php

namespace App\Rivals\Data;

final readonly class RivalMatchPick
{
    public function __construct(
        public RivalSearchCandidate $candidate,
        public float $confidence,
        public string $searchQuery,
    ) {}
}
