<?php

namespace App\Rivals;

use App\Models\Product;
use App\Rivals\Contracts\RivalMatchAdvisor;
use App\Rivals\Data\RivalMatchPick;
use App\Rivals\Data\RivalSearchCandidate;

/**
 * Heuristic “AI matching help”: search queries + confidence-ranked pick.
 * Does not invent prices — only ranking from catalog fields.
 */
class HeuristicMatchAdvisor implements RivalMatchAdvisor
{
    public function searchQueries(Product $product): array
    {
        $queries = [];

        if (filled($product->barcode)) {
            $queries[] = (string) $product->barcode;
        }

        $brandName = trim(implode(' ', array_filter([
            $product->brand,
            $product->name,
        ])));

        if ($brandName !== '') {
            $queries[] = $brandName;
        }

        if (filled($product->name) && $product->name !== $brandName) {
            $queries[] = (string) $product->name;
        }

        return array_values(array_unique($queries));
    }

    public function pickBest(Product $product, array $candidates, string $searchQuery): ?RivalMatchPick
    {
        if ($candidates === []) {
            return null;
        }

        $best = null;
        $bestScore = -1.0;

        foreach ($candidates as $candidate) {
            $score = $this->score($product, $candidate, $searchQuery);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null || $bestScore < 0.35) {
            return null;
        }

        return new RivalMatchPick(
            candidate: $best,
            confidence: round(min($bestScore, 0.99), 4),
            searchQuery: $searchQuery,
        );
    }

    private function score(Product $product, RivalSearchCandidate $candidate, string $searchQuery): float
    {
        if (filled($product->barcode) && str_contains($candidate->title, (string) $product->barcode)) {
            return 0.98;
        }

        if (filled($product->barcode) && $searchQuery === (string) $product->barcode) {
            return 0.95;
        }

        $needle = mb_strtolower(trim(implode(' ', array_filter([$product->brand, $product->name]))));
        $hay = mb_strtolower($candidate->title);

        if ($needle === '' || $hay === '') {
            return 0.0;
        }

        similar_text($needle, $hay, $percent);
        $score = $percent / 100;

        if ($needle === $hay || str_contains($hay, $needle)) {
            $score = max($score, 0.92);
        }

        if (filled($product->brand) && str_contains($hay, mb_strtolower((string) $product->brand))) {
            $score = min(0.99, $score + 0.12);
        }

        return $score;
    }
}
