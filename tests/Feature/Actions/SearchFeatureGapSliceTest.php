<?php

declare(strict_types=1);

use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\RecordSearchResultClickAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\RunSearchAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Models\SearchLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function (): void {
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10, 1);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10, 2);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(20, 1);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(20, 2);
    Schema::dropIfExists('search_logs');
});

test('expands configured synonyms in both directions', function (): void {
    config()->set('capell-search.synonyms', [
        'cms' => ['content management system'],
    ]);

    expect(ResolveExpandedSearchQueriesAction::run('cms hosting'))->toBe([
        'cms hosting',
        'content management system hosting',
    ]);

    expect(ResolveExpandedSearchQueriesAction::run('content management system hosting'))->toBe([
        'content management system hosting',
        'cms hosting',
    ]);
});

test('runs expanded synonym queries and deduplicates results by url', function (): void {
    config()->set('capell-search.synonyms', [
        'cms' => ['content management system'],
    ]);

    $search = new class implements Search
    {
        /**
         * @var list<string>
         */
        public array $queries = [];

        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $this->queries[] = $query;

            $items = match ($query) {
                'cms hosting' => [
                    new SearchResultData(
                        title: 'CMS Hosting',
                        url: '/cms-hosting',
                        excerpt: 'Hosting for CMS sites.',
                        score: 1.0,
                    ),
                ],
                'content management system hosting' => [
                    new SearchResultData(
                        title: 'CMS Hosting',
                        url: '/cms-hosting',
                        excerpt: 'Hosting for CMS sites.',
                        score: 1.0,
                    ),
                    new SearchResultData(
                        title: 'Content Management Hosting',
                        url: '/content-management-hosting',
                        excerpt: 'Hosting for content management systems.',
                        score: 1.0,
                    ),
                ],
                default => [],
            };

            return new Paginator(new Collection($items), count($items), $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    };

    $results = (new RunSearchAction($search))->handle(new SearchRequestData(query: 'CMS hosting'));

    expect($search->queries)->toBe([
        'cms hosting',
        'content management system hosting',
    ]);
    expect($results->total())->toBe(2);
    expect(collect($results->items())->pluck('url')->all())->toBe([
        '/cms-hosting',
        '/content-management-hosting',
    ]);
});

test('expands explicit and dictionary typo corrections', function (): void {
    config()->set('capell-search.typo_corrections', [
        'capel' => 'capell',
    ]);
    config()->set('capell-search.typo_terms', [
        'hosting',
    ]);

    expect(ResolveExpandedSearchQueriesAction::run('capel hostng'))->toBe([
        'capel hostng',
        'capell hostng',
        'capel hosting',
    ]);
});

test('runs typo corrected queries and deduplicates results by url', function (): void {
    config()->set('capell-search.typo_corrections', [
        'capel' => 'capell',
    ]);

    $search = new class implements Search
    {
        /**
         * @var list<string>
         */
        public array $queries = [];

        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $this->queries[] = $query;

            $items = match ($query) {
                'capel hosting' => [],
                'capell hosting' => [
                    new SearchResultData(
                        title: 'Capell Hosting',
                        url: '/capell-hosting',
                        excerpt: 'Hosting for Capell sites.',
                        score: 1.0,
                    ),
                ],
                default => [],
            };

            return new Paginator(new Collection($items), count($items), $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    };

    $results = (new RunSearchAction($search))->handle(new SearchRequestData(query: 'capel hosting'));

    expect($search->queries)->toBe([
        'capel hosting',
        'capell hosting',
    ]);
    expect(collect($results->items())->pluck('url')->all())->toBe([
        '/capell-hosting',
    ]);
});

test('promotes configured results and applies source weights', function (): void {
    config()->set('capell-search.promoted_results', [
        [
            'queries' => ['cms hosting'],
            'title' => 'Talk to CMS Hosting',
            'url' => '/contact-cms-hosting',
            'excerpt' => 'Get help choosing a CMS hosting plan.',
            'type' => 'page',
            'score' => 1000.0,
        ],
    ]);
    config()->set('capell-search.source_weights', [
        'guide' => 4.0,
    ]);

    $results = new Paginator(new Collection([
        new SearchResultData(
            title: 'CMS Hosting Page',
            url: '/cms-hosting',
            excerpt: 'Hosting overview.',
            type: 'page',
            score: 1.0,
        ),
        new SearchResultData(
            title: 'CMS Hosting Guide',
            url: '/cms-hosting-guide',
            excerpt: 'Detailed hosting guide.',
            type: 'guide',
            score: 0.5,
        ),
    ]), 2, 10, 1);

    $enhancedResults = ApplySearchResultEnhancementsAction::run($results, 'CMS hosting');

    expect((new Collection($enhancedResults->items()))->pluck('url')->all())->toBe([
        '/contact-cms-hosting',
        '/cms-hosting',
        '/cms-hosting-guide',
    ]);
    expect(($enhancedResults->items()[1] ?? null)?->score)->toBeGreaterThan(25.0);
    expect($enhancedResults->total())->toBe(3);
});

test('configured promotions deduplicate search results with the same url', function (): void {
    config()->set('capell-search.promoted_results', [
        [
            'query' => 'pricing',
            'title' => 'Pricing',
            'url' => '/pricing',
            'excerpt' => 'Plans and billing.',
        ],
    ]);

    $results = new Paginator(new Collection([
        new SearchResultData(
            title: 'Pricing',
            url: '/pricing',
            excerpt: 'Organic pricing result.',
            score: 1.0,
        ),
    ]), 1, 10, 1);

    $enhancedResults = ApplySearchResultEnhancementsAction::run($results, 'pricing');

    expect((new Collection($enhancedResults->items()))->pluck('url')->all())->toBe(['/pricing']);
    expect(($enhancedResults->items()[0] ?? null)?->excerpt)->toBe('Plans and billing.');
    expect($enhancedResults->total())->toBe(1);
});

test('click boosts use cached click aggregates for the current site and language', function (): void {
    createSearchLogsTableForEnhancementTests();
    config()->set('capell-search.ranking.click_counts_cache_seconds', 300);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10, 1);

    SearchLog::factory()->count(4)->create([
        'site_id' => 10,
        'language_id' => 1,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => '/clicked',
    ]);
    SearchLog::factory()->count(6)->create([
        'site_id' => 20,
        'language_id' => 1,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => '/clicked',
    ]);
    SearchLog::factory()->count(8)->create([
        'site_id' => 10,
        'language_id' => 2,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => '/clicked',
    ]);

    $results = new Paginator(new Collection([
        new SearchResultData(
            title: 'Clicked',
            url: '/clicked',
            excerpt: 'Clicked result.',
            score: 1.0,
        ),
    ]), 1, 10, 1);

    $aggregateQueries = 0;

    DB::listen(function (QueryExecuted $query) use (&$aggregateQueries): void {
        if (str_contains($query->sql, 'clicked_result_url') && str_contains($query->sql, 'count(*)')) {
            $aggregateQueries++;
        }
    });

    $firstRun = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);
    $secondRun = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);

    expect($aggregateQueries)->toBe(1)
        ->and(($firstRun->items()[0] ?? null)?->score)->toBe(2.0)
        ->and(($secondRun->items()[0] ?? null)?->score)->toBe(2.0);
});

test('cached click aggregates preserve language scoped result ranking', function (): void {
    createSearchLogsTableForEnhancementTests();
    config()->set('capell-search.ranking.click_counts_cache_seconds', 300);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10, 1);

    SearchLog::factory()->count(5)->create([
        'site_id' => 10,
        'language_id' => 1,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => '/clicked',
    ]);
    SearchLog::factory()->count(10)->create([
        'site_id' => 10,
        'language_id' => 2,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => '/other-language-clicked',
    ]);

    $results = new Paginator(new Collection([
        new SearchResultData(
            title: 'Clicked',
            url: '/clicked',
            excerpt: 'Clicked result.',
            score: 1.0,
        ),
        new SearchResultData(
            title: 'Other Language Clicked',
            url: '/other-language-clicked',
            excerpt: 'Other language clicked result.',
            score: 1.5,
        ),
    ]), 2, 10, 1);

    $firstRun = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);
    $secondRun = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);

    expect((new Collection($firstRun->items()))->pluck('url')->all())->toBe([
        '/clicked',
        '/other-language-clicked',
    ]);
    expect((new Collection($secondRun->items()))->pluck('url')->all())->toBe([
        '/clicked',
        '/other-language-clicked',
    ]);
    expect(($secondRun->items()[0] ?? null)?->score)->toBe(2.25)
        ->and(($secondRun->items()[1] ?? null)?->score)->toBe(1.5);
});

test('recording a click flushes cached click aggregates', function (): void {
    createSearchLogsTableForEnhancementTests();
    config()->set('capell-search.ranking.click_counts_cache_seconds', 300);
    ApplySearchResultEnhancementsAction::forgetClickCountsCache(10, 1);

    $log = SearchLog::factory()->create([
        'site_id' => 10,
        'language_id' => 1,
        'query' => 'Capell',
        'normalized_query' => 'capell',
        'clicked_result_url' => null,
    ]);

    $results = new Paginator(new Collection([
        new SearchResultData(
            title: 'Clicked',
            url: '/clicked',
            excerpt: 'Clicked result.',
            score: 1.0,
        ),
    ]), 1, 10, 1);

    $beforeClick = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);

    RecordSearchResultClickAction::run($log, '/clicked');

    $afterClick = ApplySearchResultEnhancementsAction::run($results, 'capell', 10, 1);

    expect(($beforeClick->items()[0] ?? null)?->score)->toBe(1.0)
        ->and(($afterClick->items()[0] ?? null)?->score)->toBe(1.25);
});

function createSearchLogsTableForEnhancementTests(): void
{
    config()->set('capell-search.logs.table_name', 'search_logs');

    Schema::dropIfExists('search_logs');
    Schema::create('search_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->index();
        $table->foreignId('language_id')->nullable()->index();
        $table->string('query');
        $table->string('normalized_query')->index();
        $table->unsignedInteger('results_count')->default(0);
        $table->string('clicked_result_url')->nullable();
        $table->string('ip_hash', 64)->nullable();
        $table->string('user_agent_hash', 64)->nullable();
        $table->timestamp('searched_at')->index();
        $table->timestamps();
    });
}
