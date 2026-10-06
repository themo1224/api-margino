<?php

use App\Models\Product;
use App\Rivals\Data\RivalSearchCandidate;
use App\Rivals\HeuristicMatchAdvisor;

it('searchQueries prefers barcode then brand+name then name', function () {
    $product = new Product([
        'barcode' => '6261234567890',
        'brand' => 'Acme',
        'name' => 'Sample Perfume',
    ]);

    expect(app(HeuristicMatchAdvisor::class)->searchQueries($product))->toBe([
        '6261234567890',
        'Acme Sample Perfume',
        'Sample Perfume',
    ]);
});

it('pickBest returns null for empty candidates', function () {
    $product = new Product(['name' => 'Sample']);

    expect(app(HeuristicMatchAdvisor::class)->pickBest($product, [], 'Sample'))->toBeNull();
});

it('pickBest returns null when best score below 0.35', function () {
    $product = new Product(['name' => 'عطر تست', 'brand' => 'Acme']);
    $candidates = [
        new RivalSearchCandidate(
            listingIdentity: 'x',
            title: 'completely unrelated widget xyz',
        ),
    ];

    expect(app(HeuristicMatchAdvisor::class)->pickBest($product, $candidates, 'عطر تست'))->toBeNull();
});

it('barcode in candidate title scores high and is pickable', function () {
    $product = new Product([
        'barcode' => '6261234567890',
        'name' => 'Sample',
    ]);
    $candidates = [
        new RivalSearchCandidate(
            listingIdentity: 'barcoded',
            title: 'Item 6261234567890 official',
        ),
    ];

    $pick = app(HeuristicMatchAdvisor::class)->pickBest($product, $candidates, 'Sample');

    expect($pick)->not->toBeNull()
        ->and($pick->confidence)->toBeGreaterThanOrEqual(0.98)
        ->and($pick->candidate->listingIdentity)->toBe('barcoded');
});

it('barcode-only search query scores 0.95', function () {
    $product = new Product([
        'barcode' => '6261234567890',
        'name' => 'Sample',
    ]);
    $candidates = [
        new RivalSearchCandidate(
            listingIdentity: 'no-barcode-in-title',
            title: 'Some other listing without the code',
        ),
    ];

    $pick = app(HeuristicMatchAdvisor::class)->pickBest($product, $candidates, '6261234567890');

    expect($pick)->not->toBeNull()
        ->and($pick->confidence)->toBe(0.95);
});

it('exact or contains title boosts to at least 0.92', function () {
    $product = new Product([
        'brand' => 'Acme',
        'name' => 'Sample Perfume',
    ]);
    $candidates = [
        new RivalSearchCandidate(
            listingIdentity: 'exact',
            title: 'Acme Sample Perfume',
        ),
    ];

    $pick = app(HeuristicMatchAdvisor::class)->pickBest($product, $candidates, 'Acme Sample Perfume');

    expect($pick)->not->toBeNull()
        ->and($pick->confidence)->toBeGreaterThanOrEqual(0.92);
});

it('pickBest chooses highest scoring candidate', function () {
    $product = new Product([
        'barcode' => '6269998887776',
        'name' => 'Widget',
    ]);
    $candidates = [
        new RivalSearchCandidate(
            listingIdentity: 'weak',
            title: 'unrelated thing',
        ),
        new RivalSearchCandidate(
            listingIdentity: 'strong',
            title: 'Listing with 6269998887776 inside',
        ),
    ];

    $pick = app(HeuristicMatchAdvisor::class)->pickBest($product, $candidates, 'Widget');

    expect($pick)->not->toBeNull()
        ->and($pick->candidate->listingIdentity)->toBe('strong');
});
