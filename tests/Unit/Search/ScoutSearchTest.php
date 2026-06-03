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
