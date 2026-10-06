<?php

namespace App\Rivals\Contracts;

use App\Models\Product;
use App\Rivals\Data\RivalMatchPick;
use App\Rivals\Data\RivalSearchCandidate;

interface RivalMatchAdvisor
{
    /**
     * @return list<string>
     */
    public function searchQueries(Product $product): array;

    /**
     * @param  list<RivalSearchCandidate>  $candidates
     */
    public function pickBest(Product $product, array $candidates, string $searchQuery): ?RivalMatchPick;
}
