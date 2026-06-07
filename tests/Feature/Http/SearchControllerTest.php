<?php

declare(strict_types=1);

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Http\Controllers\SearchController;
use Capell\Search\Models\SearchLog;
use Capell\Search\Providers\SearchServiceProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.results_per_page', 5);
    config()->set('capell-search.minimum_query_length', 2);
});

test('autocomplete returns no results for blank or too-short queries', function (string $query): void {
    app()->instance(Search::class, new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            throw new RuntimeException('Search should not run for blank or too-short autocomplete queries.');
        }

        public function highlight(string $text, string $query): string
        {
            return str_replace('Search', '<mark>Search</mark>', e($text));
        }
    });

    $request = Request::create('/search/autocomplete', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => $query]);
    $response = (new SearchController)->autocomplete($request);

    expect($response->getData(true))->toMatchArray([
        'query' => $query,
        'minimumLength' => 2,
        'results' => [],
        'allResultsUrl' => route('capell-frontend.search', ['q' => $query]),
    ]);
})->with(['', 'c']);

test('autocomplete returns limited public-safe results without writing search logs', function (): void {
    config()->set('capell-search.autocomplete.limit', 1);
    config()->set('capell-search.type_labels', [
        'marketing_content' => 'Marketing',
    ]);

    $recordedSearch = new stdClass;
    $recordedSearch->perPage = null;
    $recordedSearch->page = null;

    app()->instance(Search::class, new readonly class($recordedSearch) implements Search
    {
        public function __construct(private stdClass $recordedSearch) {}

        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $this->recordedSearch->perPage = $perPage;
            $this->recordedSearch->page = $page;

            return new Paginator(new Collection([
                new SearchResultData(
                    title: 'Capell CMS',
                    url: '/capell-cms',
                    excerpt: 'Capell CMS result',
                    type: 'marketing_content',
                    score: 4.0,
                ),
                new SearchResultData(
                    title: 'Hidden extra',
                    url: '/hidden-extra',
                    excerpt: 'Should be limited away',
                    type: 'marketing_content',
                    score: 1.0,
                ),
            ]), 2, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return str_replace('Search', '<mark>Search</mark>', e($text));
        }
    });

    $request = Request::create('/search/autocomplete', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'Capell']);
    $response = (new SearchController)->autocomplete($request);
    $payload = $response->getData(true);

    expect($recordedSearch->perPage)->toBe(1)
        ->and($recordedSearch->page)->toBe(1)
        ->and($payload)->toHaveKeys(['query', 'minimumLength', 'results', 'allResultsUrl'])
        ->and($payload['results'])->toHaveCount(1)
        ->and($payload['results'][0])->toBe([
            'title' => 'Capell CMS',
            'url' => '/capell-cms',
            'excerpt' => 'Capell CMS result',
            'type' => 'marketing_content',
            'typeLabel' => 'Marketing',
            'score' => 4,
        ])
        ->and($payload['results'][0])->not->toHaveKeys(['id', 'model', 'modelClass', 'adminUrl', 'signedUrl'])
        ->and(SearchLog::query()->count())->toBe(0);
});

test('autocomplete route is lightly throttled', function (): void {
    $route = Route::getRoutes()->getByName('capell-frontend.search.autocomplete');

    expect($route?->gatherMiddleware())->toContain('throttle:capell-search-autocomplete');
});

test('click tracking route is csrf exempt for cached frontend beacons', function (): void {
    $route = Route::getRoutes()->getByName('capell-frontend.search.click');

    expect($route?->gatherMiddleware())->toContain('throttle:capell-search-clicks')
        ->and($route?->excludedMiddleware())->toContain(VerifyCsrfToken::class);
});

test('header click beacon does not require a csrf token', function (): void {
    $html = view('capell-search::components.header.search-dialog')->render();

    expect($html)
        ->toContain("mode: 'no-cors'")
        ->not()->toContain('csrf-token')
        ->not()->toContain('X-CSRF-TOKEN');
});

test('result click beacon does not require a csrf token', function (): void {
    $html = view('capell-search::components.results', [
        'query' => '',
        'results' => new Paginator(new Collection, 0, 5, 1),
    ])->render();

    expect($html)
        ->toContain('window.capellSearchClickBeaconInitialized')
        ->toContain("mode: 'no-cors'")
        ->not()->toContain('csrf-token')
        ->not()->toContain('X-CSRF-TOKEN');
});

test('controller uses configured page view when it exists', function (): void {
    config()->set('capell-search.page_view', 'capell-search::components.form');

    app()->instance(Search::class, new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            return new Paginator(new Collection, 0, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'Capell']);
    $view = (new SearchController)($request);
    $html = $view->render();

    expect($view->name())->toBe('capell-search::components.form')
        ->and($view->getData()['query'])->toBe('Capell')
        ->and($html)->toContain('Search this site')
        ->and($html)->toContain('placeholder="Search pages, services, and resources"')
        ->and($html)->toContain('placeholder:text-outline-variant')
        ->and($html)->toContain('text-primary-on');
});

test('controller returns the search page view with an empty paginator for a blank query', function (): void {
    app()->instance(Search::class, new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            throw new RuntimeException('Search should not run for a blank query.');
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => '   ']);
    $view = (new SearchController)($request);

    expect($view->name())->toBe('capell-search::pages.search');
    expect($view->getData()['query'])->toBe('   ');
    expect($view->getData()['results'])->toBeInstanceOf(LengthAwarePaginator::class);
    expect($view->getData()['results']->total())->toBe(0);
});

test('controller passes normalized valid searches to the site search service', function (): void {
    $recordedSearch = new stdClass;
    $recordedSearch->queries = [];

    app()->instance(Search::class, new readonly class($recordedSearch) implements Search
    {
        public function __construct(private stdClass $recordedSearch) {}

        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $this->recordedSearch->queries[] = $query;

            $results = new Collection([
                new SearchResultData(
                    title: 'Laravel Search',
                    url: '/laravel-search',
                    excerpt: 'Search result content',
                ),
            ]);

            return new Paginator($results, 1, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return str_replace('Search', '<mark>Search</mark>', e($text));
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => '  Laravel   Search  ', 'page' => '2']);
    $view = (new SearchController)($request);

    expect($recordedSearch->queries)->toBe(['laravel search']);
    expect($view->getData()['results']->total())->toBe(1);
    expect($view->getData()['results']->currentPage())->toBe(2);
    expect($view->getData()['results']->perPage())->toBe(5);
    expect($view->getData()['highlightedResults']->first())->toBe([
        'title' => 'Laravel <mark>Search</mark>',
        'excerpt' => '<mark>Search</mark> result content',
    ]);
    expect($view->render())->toContain('Laravel <mark>Search</mark>');
});

test('controller defers search log writes until after the response', function (): void {
    app()->instance(Search::class, new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            return new Paginator(new Collection([
                new SearchResultData(
                    title: 'Laravel Search',
                    url: '/laravel-search',
                    excerpt: 'Search result content',
                ),
            ]), 1, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'Laravel Search'], server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Capell Test Browser',
    ]);

    (new SearchController)($request);

    expect(SearchLog::query()->count())->toBe(0);

    app()->terminate();

    $log = SearchLog::query()->first();

    expect(SearchLog::query()->count())->toBe(1)
        ->and($log)->toBeInstanceOf(SearchLog::class);

    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected deferred search log.');

    $appKey = config('app.key');
    throw_unless(is_string($appKey), RuntimeException::class, 'Expected app key string.');

    expect($log->query)->toBe('Laravel Search')
        ->and($log->results_count)->toBe(1)
        ->and($log->ip_hash)->toBe(hash('sha256', '203.0.113.10|' . $appKey))
        ->and($log->user_agent_hash)->toBe(hash('sha256', 'Capell Test Browser|' . $appKey));
});

test('public search markup does not expose package identifiers', function (): void {
    app()->instance(Search::class, new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            return new Paginator(new Collection([
                new SearchResultData(
                    title: 'Laravel Search',
                    url: '/laravel-search',
                    excerpt: 'Search result content',
                ),
            ]), 1, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return e($text);
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'Laravel']);
    $html = (new SearchController)($request)->render();

    expect($html)
        ->not()->toContain('capell-search')
        ->not()->toContain('capell-header-search')
        ->not()->toContain('package')
        ->not()->toContain('editor');
});
