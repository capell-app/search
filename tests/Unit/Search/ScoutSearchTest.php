<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Drivers\ScoutSearch;
use Capell\Search\Support\SearchableSourceRegistry;
use Capell\Search\Tests\Fixtures\SearchAdditionalCoverageScoutModel;
use Illuminate\Pagination\LengthAwarePaginator;

test('ScoutSearch implements Search', function (): void {
    expect(ScoutSearch::class)->toImplement(Search::class);
});

test('search returns empty paginator for blank query', function (): void {
    $search = new ScoutSearch(Page::class, 'slug');
    $results = $search->search('   ');

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($results->total())->toBe(0);
    expect($results->isEmpty())->toBeTrue();
});

test('highlight wraps keyword in mark tags', function (): void {
    $search = new ScoutSearch(Page::class, 'slug');
    $html = $search->highlight('<b>Meilisearch</b> is fast', 'Meilisearch');

    expect($html)->toContain('<mark>Meilisearch</mark>');
    expect($html)->toContain('&lt;b&gt;');
});

test('highlight returns escaped text when query is empty', function (): void {
    $search = new ScoutSearch(Page::class, 'slug');
    $html = $search->highlight('<script>alert(1)</script>', '');

    expect($html)->toContain('&lt;script&gt;');
    expect($html)->not->toContain('<mark>');
});

test('searches every enabled registered source and merges results', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'primary',
        label: 'Primary',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'primary',
        enabledByDefault: true,
        weight: 2.0,
    ));
    $registry->register(new SearchableSourceData(
        key: 'disabled',
        label: 'Disabled',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'disabled',
        enabledByDefault: false,
    ));

    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Search',
            'excerpt' => 'Search package result.',
            'slug' => 'capell-search',
        ],
    ]);

    $results = (new ScoutSearch($registry))->search('Capell');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->url)->toBe('/capell-search')
        ->and($results->items()[0]->type)->toBe('primary')
        ->and($results->items()[0]->score)->toBe(2.0);
});

test('honors engine relevance scores when ranking scout results', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'primary',
        label: 'Primary',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'primary',
        enabledByDefault: true,
    ));

    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Search Local Winner',
            'excerpt' => 'Capell Capell Capell',
            'slug' => 'local-score',
            '_rankingScore' => 0.2,
        ],
        [
            'title' => 'Capell Search Engine Winner',
            'excerpt' => 'Capell',
            'slug' => 'engine-score',
            '_rankingScore' => 0.9,
        ],
    ]);

    $results = (new ScoutSearch($registry))->search('Capell');

    expect(collect($results->items())->pluck('url')->all())->toBe([
        '/engine-score',
        '/local-score',
    ])
        ->and($results->items()[0]->score)->toBe(0.9);
});

test('preserves scout engine totals beyond the fetched page window', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'primary',
        label: 'Primary',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'primary',
        enabledByDefault: true,
    ));

    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Search',
            'excerpt' => 'Search package result.',
            'slug' => 'capell-search',
            '__engine_total' => 37,
        ],
    ]);

    $results = (new ScoutSearch($registry))->search('Capell', perPage: 1);

    expect($results->total())->toBe(37)
        ->and($results->items())->toHaveCount(1);
});

test('preserves absolute urls from searchable payloads', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'primary',
        label: 'Primary',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'primary',
        enabledByDefault: true,
    ));

    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Search',
            'excerpt' => 'Search package result.',
            'url' => 'https://capell-app.test/search-result',
        ],
    ]);

    $results = (new ScoutSearch($registry))->search('Capell');

    expect($results->items()[0]->url)->toBe('https://capell-app.test/search-result');
});

test('excludes unpublished and private payloads from public Scout results', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'primary',
        label: 'Primary',
        modelClass: SearchAdditionalCoverageScoutModel::class,
        type: 'primary',
        enabledByDefault: true,
    ));

    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Public Search',
            'excerpt' => 'Search package result.',
            'slug' => 'capell-public-search',
            'status' => 'published',
            'is_private' => false,
        ],
        [
            'title' => 'Capell Draft Search',
            'excerpt' => 'Draft search package result.',
            'slug' => 'capell-draft-search',
            'status' => 'draft',
            'is_private' => false,
        ],
        [
            'title' => 'Capell Private Search',
            'excerpt' => 'Private search package result.',
            'slug' => 'capell-private-search',
            'status' => 'published',
            'is_private' => true,
        ],
        [
            'title' => 'Capell Restricted Search',
            'excerpt' => 'Restricted search package result.',
            'slug' => 'capell-restricted-search',
            'visibility' => 'private',
        ],
    ]);

    $results = (new ScoutSearch($registry))->search('Capell');

    expect($results->total())->toBe(1)
        ->and(collect($results->items())->pluck('url')->all())->toBe(['/capell-public-search']);
});
