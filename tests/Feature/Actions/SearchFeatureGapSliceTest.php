<?php

declare(strict_types=1);

use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\RunSearchAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

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
        '/cms-hosting-guide',
        '/cms-hosting',
    ]);
    expect(($enhancedResults->items()[1] ?? null)?->score)->toBe(2.0);
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
