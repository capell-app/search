<?php

declare(strict_types=1);

use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\Search\Actions\RunSearchAction;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Drivers\ScoutSearch;
use Capell\Search\Drivers\SiteDiscoverySearch;
use Capell\Search\Support\ExpandedSearchPaginator;
use Capell\Search\Tests\Fixtures\BoundedSearch;
use Capell\Search\Tests\Fixtures\RecordingSearch;
use Capell\Search\Tests\Fixtures\SearchAdditionalCoverageScoutModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('expanded pagination keeps unique results and bounded scoped calls across drivers', function (string $driver): void {
    config()->set('capell-search.synonyms', ['cms' => ['platform']]);
    config()->set('capell-search.promoted_results', []);

    $rows = [
        ['title' => 'cms one', 'slug' => 'one', 'site_id' => 1, 'language_id' => 1],
        ['title' => 'cms platform shared', 'slug' => 'shared', 'site_id' => 1, 'language_id' => 1],
        ['title' => 'platform only', 'slug' => 'only', 'site_id' => 1, 'language_id' => 1],
        ['title' => 'platform other', 'slug' => 'other', 'site_id' => 2, 'language_id' => 1],
    ];

    if ($driver === 'database') {
        Schema::create('expanded_search_test_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('language_id');
            $table->string('status')->default('published');
        });
        DB::table('expanded_search_test_pages')->insert($rows);
        $implementation = new DatabaseSearch(DB::connection(), 'expanded_search_test_pages', ['title']);
    } elseif ($driver === 'scout') {
        SearchAdditionalCoverageScoutModel::fakeRecords(array_values(array_map(static fn (array $row): array => $row + ['is_public' => true], $rows)));
        $implementation = new ScoutSearch(SearchAdditionalCoverageScoutModel::class, 'slug');
    } else {
        $implementation = new SiteDiscoverySearch(collect($rows)->map(static fn (array $row): PublicUrlRegistryEntryData => new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.test/' . $row['slug'],
            sourcePackage: 'fixture',
            siteKey: $row['site_id'],
            languageKey: $row['language_id'],
            siteId: $row['site_id'],
            languageId: $row['language_id'],
            title: $row['title'],
        )));
    }

    try {
        $search = new RecordingSearch($implementation);
        $pages = [];
        foreach ([1, 2] as $page) {
            $pages[] = runBoundAction(RunSearchAction::class, new RunSearchAction($search), new SearchRequestData(
                query: 'cms',
                page: $page,
                perPage: 2,
                siteId: 1,
                languageId: 1,
            ));
        }

        /** @var list<string> $urls */
        $urls = collect($pages)->flatMap(static fn ($page) => collect($page->items())->pluck('url'))->all();

        expect($pages[0])->toBeInstanceOf(ExpandedSearchPaginator::class)
            ->and($pages[0]->total())->toBe(3)
            ->and($pages[1]->total())->toBe(3)
            ->and($urls)->toHaveCount(3)
            ->and(array_unique($urls))->toHaveCount(3)
            ->and(implode(' ', $urls))->toContain('only')->not->toContain('other')
            ->and($search->calls)->toHaveCount(4);

        foreach ($search->calls as $call) {
            expect($call['perPage'])->toBe(100)
                ->and($call['page'])->toBe(1)
                ->and($call['siteId'])->toBe(1)
                ->and($call['languageId'])->toBe(1);
        }
    } finally {
        if ($driver === 'database') {
            Schema::dropIfExists('expanded_search_test_pages');
        }
    }
})->with(['database', 'site discovery', 'scout']);

test('expanded windows remain bounded and navigable when each driver has more than one hundred matches', function (string $driver): void {
    config()->set('capell-search.synonyms', ['cms' => ['platform']]);
    config()->set('capell-search.promoted_results', []);

    $rows = [];
    foreach (range(1, 10) as $index) {
        $rows[] = ['title' => 'cms platform shared', 'slug' => sprintf('a-shared-%03d', $index), 'site_id' => 1, 'language_id' => 1];
    }
    foreach (range(1, 95) as $index) {
        $rows[] = ['title' => 'cms primary', 'slug' => sprintf('b-primary-%03d', $index), 'site_id' => 1, 'language_id' => 1];
    }
    foreach (range(1, 95) as $index) {
        $rows[] = ['title' => 'platform synonym', 'slug' => sprintf('c-synonym-%03d', $index), 'site_id' => 1, 'language_id' => 1];
    }

    if ($driver === 'database') {
        Schema::create('expanded_search_window_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('language_id');
            $table->string('status')->default('published');
        });
        DB::table('expanded_search_window_pages')->insert($rows);
        $implementation = new DatabaseSearch(DB::connection(), 'expanded_search_window_pages', ['title']);
    } elseif ($driver === 'scout') {
        SearchAdditionalCoverageScoutModel::fakeRecords(array_values(array_map(static fn (array $row): array => $row + ['is_public' => true], $rows)));
        $implementation = new ScoutSearch(SearchAdditionalCoverageScoutModel::class, 'slug');
    } else {
        $implementation = new SiteDiscoverySearch(collect($rows)->map(static fn (array $row): PublicUrlRegistryEntryData => new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.test/' . $row['slug'],
            sourcePackage: 'fixture',
            siteKey: 1,
            languageKey: 1,
            siteId: 1,
            languageId: 1,
            title: $row['title'],
        )));
    }

    try {
        $search = new RecordingSearch($implementation);
        $urls = [];
        $laterUrls = [];
        foreach (range(1, 19) as $page) {
            $results = runBoundAction(RunSearchAction::class, new RunSearchAction($search), new SearchRequestData(
                query: 'cms',
                page: $page,
                perPage: 10,
                siteId: 1,
                languageId: 1,
            ));
            /** @var list<string> $pageUrls */
            $pageUrls = collect($results->items())->pluck('url')->all();
            assert($results instanceof ExpandedSearchPaginator);
            expect($results->truncated)->toBeTrue()
                ->and($results->total())->toBe(190)
                ->and($pageUrls)->toHaveCount(10);
            array_push($urls, ...$pageUrls);
            if ($page > 1) {
                array_push($laterUrls, ...$pageUrls);
            }
        }

        expect(array_unique($urls))->toHaveCount(190)
            ->and(implode(' ', $laterUrls))->toContain('c-synonym-')
            ->and($search->calls)->toHaveCount(38);
        foreach ($search->calls as $call) {
            expect($call['perPage'])->toBe(100)->and($call['page'])->toBe(1);
        }
    } finally {
        if ($driver === 'database') {
            Schema::dropIfExists('expanded_search_window_pages');
        }
    }
})->with(['database', 'site discovery', 'scout']);

test('reports a bounded total and paginates promotions without repeating their organic urls', function (): void {
    config()->set('capell-search.synonyms', ['cms' => ['platform']]);
    config()->set('capell-search.promoted_results', [[
        'query' => 'cms', 'title' => 'Promoted', 'url' => '/result-1', 'excerpt' => '',
    ]]);
    $search = new RecordingSearch(new BoundedSearch);
    /** @var list<string> $urls */
    $urls = [];

    foreach (range(1, 10) as $page) {
        $result = runBoundAction(RunSearchAction::class, new RunSearchAction($search), new SearchRequestData(query: 'cms', page: $page, perPage: 10));
        assert($result instanceof ExpandedSearchPaginator);
        expect($result->total())->toBe(100)->and($result->truncated)->toBeTrue();
        /** @var list<string> $pageUrls */
        $pageUrls = collect($result->items())->pluck('url')->all();
        array_push($urls, ...$pageUrls);
    }

    expect($urls)->toHaveCount(100)
        ->and(array_unique($urls))->toHaveCount(100)
        ->and($urls[0])->toBe('/result-1')
        ->and($search->calls)->toHaveCount(20);

    foreach ($search->calls as $call) {
        expect($call['perPage'])->toBe(100)->and($call['page'])->toBe(1);
    }
});

test('the results view explains that an expanded result window can be incomplete', function (): void {
    $results = new ExpandedSearchPaginator([], 0, 10, 1, true);

    $html = view('capell-search::components.results', ['results' => $results, 'query' => 'cms'])->render();

    expect($html)->toContain(__('capell-search::generic.results_limited'));
});
