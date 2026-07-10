<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Search\Actions\BuildSearchPageViewDataAction;
use Capell\Search\Actions\GenerateSearchClickTokenAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Http\Controllers\SearchController;
use Capell\Search\Models\SearchLog;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.results_per_page', 5);
    config()->set('capell-search.minimum_query_length', 2);
});

afterEach(function (): void {
    Schema::dropIfExists('search_logs');
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
        'allResultsUrl' => route('capell-frontend.search', ['q' => $query], false),
    ]);
})->with(['', 'c']);

test('autocomplete returns limited public-safe results without writing search logs', function (): void {
    config()->set('capell-search.autocomplete.limit', 1);
    config()->set('capell-search.promoted_results', [
        [
            'query' => 'capell',
            'title' => 'Promoted Capell result',
            'url' => '/promoted-capell',
            'excerpt' => 'This should not be injected into autocomplete.',
            'type' => 'page',
        ],
    ]);
    config()->set('capell-search.type_labels', [
        'marketing_content' => 'Marketing',
    ]);

    $recordedSearch = new stdClass;
    $recordedSearch->query = null;
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
            $this->recordedSearch->query = $query;
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
    $payload = searchControllerJsonPayload($response);
    $results = searchControllerPayloadResults($payload);

    expect($recordedSearch->query)->toBe('capell')
        ->and($recordedSearch->perPage)->toBe(1)
        ->and($recordedSearch->page)->toBe(1)
        ->and($payload)->toHaveKeys(['query', 'minimumLength', 'results', 'allResultsUrl'])
        ->and($results)->toHaveCount(1)
        ->and($results[0])->toBe([
            'title' => 'Capell CMS',
            'url' => '/capell-cms',
            'excerpt' => 'Capell CMS result',
            'type' => 'marketing_content',
            'typeLabel' => 'Marketing',
            'score' => 4,
        ])
        ->and($results[0])->not->toHaveKeys(['id', 'model', 'modelClass', 'adminUrl', 'signedUrl'])
        ->and(SearchLog::query()->count())->toBe(0);
});

test('autocomplete returns corrected query metadata and popular query suggestions', function (): void {
    config()->set('capell-search.autocomplete.limit', 5);
    config()->set('capell-search.typo_corrections', [
        'capel' => 'capell',
    ]);

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

    SearchLog::query()->insert([
        [
            'query' => 'Capel migration',
            'normalized_query' => 'capel migration',
            'results_count' => 2,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'query' => 'Capel marketplace',
            'normalized_query' => 'capel marketplace',
            'results_count' => 3,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

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

    $request = Request::create('/search/autocomplete', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'capel']);
    $payload = searchControllerJsonPayload((new SearchController)->autocomplete($request));
    $metadata = searchControllerPayloadArray($payload, 'metadata');
    $suggestions = searchControllerPayloadSuggestions($payload);

    expect($metadata['corrected'] ?? null)->toBe('capell')
        ->and($suggestions)->toHaveCount(2)
        ->and($suggestions[0]['query'] ?? null)->toBe('capel marketplace')
        ->and($suggestions[0]['url'] ?? null)->toBe(route('capell-frontend.search', ['q' => 'capel marketplace'], false));
});

test('autocomplete route is lightly throttled', function (): void {
    $route = Route::getRoutes()->getByName('capell-frontend.search.autocomplete');

    expect($route?->gatherMiddleware())->toContain('throttle:capell-search-autocomplete');
});

test('full search route is throttled', function (): void {
    $route = Route::getRoutes()->getByName('capell-frontend.search');

    expect($route?->gatherMiddleware())->toContain('throttle:capell-search-requests');
});

test('click tracking route is csrf exempt for cached frontend beacons', function (): void {
    $route = Route::getRoutes()->getByName('capell-frontend.search.click');

    expect($route?->gatherMiddleware())->toContain('throttle:capell-search-clicks')
        ->and($route?->excludedMiddleware())->toContain(VerifyCsrfToken::class);
});

test('header click beacon does not require a csrf token', function (): void {
    $html = view('capell-search::components.header.search-dialog')->render();
    $script = file_get_contents(__DIR__ . '/../../../resources/dist/search-modal.js');

    throw_unless(is_string($script), RuntimeException::class, 'Expected search modal script to be readable.');

    expect($html)
        ->toContain('vendor/capell-search/search-modal.js')
        ->not()->toContain('csrf-token')
        ->not()->toContain('X-CSRF-TOKEN')
        ->and($script)
        ->toContain("mode: 'no-cors'")
        ->not()->toContain('csrf-token')
        ->not()->toContain('X-CSRF-TOKEN');
});

test('result click beacon does not require a csrf token', function (): void {
    $html = view('capell-search::components.results', [
        'clickTrackingToken' => 'opaque-token',
        'query' => '',
        'results' => new Paginator(new Collection, 0, 5, 1),
    ])->render();

    expect($html)
        ->toContain('window.capellSearchClickBeaconInitialized')
        ->toContain("mode: 'no-cors'")
        ->toContain("body.set(\n                    'token',")
        ->not()->toContain('csrf-token')
        ->not()->toContain('X-CSRF-TOKEN');
});

test('click tracking endpoint records result clicks by token', function (): void {
    RateLimiter::for('capell-search-clicks', static fn (): Limit => Limit::perMinute(120));

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

    $searchData = new SearchRequestData(query: 'Laravel Search');
    $log = SearchLog::query()->create([
        'query' => 'Laravel Search',
        'normalized_query' => 'laravel search',
        'results_count' => 1,
        'searched_at' => now(),
    ]);
    $token = GenerateSearchClickTokenAction::run($searchData);

    $this
        ->withoutMiddleware()
        ->post(route('capell-frontend.search.click'), [
            'query' => 'Laravel Search',
            'url' => '/laravel-search',
            'token' => $token,
        ])
        ->assertNoContent();

    expect($log->refresh()->clicked_result_url)->toBe('/laravel-search');
});

test('click tracking endpoint enforces its configured rate limiter', function (): void {
    RateLimiter::for('capell-search-clicks', static fn (): Limit => Limit::perMinute(1)->by('search-click-test'));

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

    Route::post('/_search-click-rate-limit-test', [SearchController::class, 'click'])
        ->middleware('throttle:capell-search-clicks')
        ->withoutMiddleware([VerifyCsrfToken::class]);

    $payload = [
        'query' => 'Laravel Search',
        'url' => '/laravel-search',
    ];

    $this->post('/_search-click-rate-limit-test', $payload)->assertNoContent();
    $this->post('/_search-click-rate-limit-test', $payload)->assertTooManyRequests();
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
        ->and($html)->toContain('placeholder="Search pages, extensions, resources, and media"')
        ->and($html)->toContain('placeholder:text-outline-variant')
        ->and($html)->toContain('text-primary-on');
});

test('builds reusable search page view data through an action boundary', function (): void {
    config()->set('capell-search.filters.enabled', false);

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
            return str_replace('Search', '<mark>Search</mark>', e($text));
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => 'Laravel Search']);
    $viewData = BuildSearchPageViewDataAction::run($request);
    $payload = $viewData->toViewData();

    expect($viewData->query)->toBe('Laravel Search')
        ->and($viewData->results->total())->toBe(1)
        ->and($viewData->highlightedResults->first())->toBe([
            'title' => 'Laravel <mark>Search</mark>',
            'excerpt' => '<mark>Search</mark> result content',
        ])
        ->and($viewData->clickTrackingToken)->toBeString()
        ->and($payload)->toHaveKeys([
            'highlightedResults',
            'facetGroups',
            'clickTrackingToken',
            'query',
            'results',
        ]);
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

test('controller does not hide frontend shell failures behind a broad throwable catch', function (): void {
    $source = file_get_contents(__DIR__ . '/../../../src/Http/Controllers/SearchController.php');

    throw_unless(is_string($source), RuntimeException::class, 'Expected SearchController source to be readable.');

    expect($source)
        ->not()->toContain('use Throwable;')
        ->not()->toContain('catch (Throwable)');
});

test('controller wraps the search page in the frontend shell when context is available', function (): void {
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

    $site = new Site;
    $site->setRawAttributes(['id' => 1, 'name' => 'Capell']);

    $language = new Language;
    $language->setRawAttributes(['id' => 1, 'name' => 'English', 'code' => 'en']);

    $page = new Page;
    $page->setRawAttributes(['id' => 1, 'name' => 'Search']);

    $layout = new Layout;
    $layout->setRawAttributes([
        'id' => 1,
        'name' => 'Default',
        'meta' => json_encode(['container' => 'xl'], JSON_THROW_ON_ERROR),
    ]);

    $theme = new Theme;
    $theme->setRawAttributes([
        'id' => 1,
        'name' => 'Minimal',
        'meta' => json_encode(['header' => false, 'footer' => false], JSON_THROW_ON_ERROR),
    ]);

    app()->instance(
        CapellFrontendContext::class,
        new CapellFrontendContext(
            (new FrontendState)
                ->withSite($site)
                ->withLanguage($language)
                ->withPage($page)
                ->withLayout($layout)
                ->withTheme($theme),
        ),
    );

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => '']);
    $view = (new SearchController)($request);

    expect($view->name())->toBe('capell::app')
        ->and($view->getData()['site'])->toBe($site)
        ->and($view->getData()['language'])->toBe($language)
        ->and($view->getData()['pageRecord'])->toBe($page)
        ->and($view->getData()['layout'])->toBe($layout)
        ->and($view->getData()['theme'])->toBe($theme)
        ->and((string) $view->getData()['slot'])->toContain('search-layout')
        ->and((string) $view->getData()['slot'])->toContain('Search this site');
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
    expect($view->getData()['results']->url(3))->toBe('http://localhost/search?page=3');
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

test('controller renders public filter facets with live counts', function (): void {
    config()->set('capell-search.searchables', [
        'pages' => [
            'label' => 'Pages',
            'model' => Model::class,
            'type' => 'page',
            'enabled' => true,
        ],
        'articles' => [
            'label' => 'Articles',
            'model' => Model::class,
            'type' => 'article',
            'enabled' => true,
        ],
    ]);

    app()->forgetInstance(SearchableSourceRegistry::class);
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
            $total = match (true) {
                $filters?->types === ['page'] => 2,
                $filters?->types === ['article'] => 1,
                $filters?->sourceKeys === ['pages'] => 2,
                $filters?->sourceKeys === ['articles'] => 1,
                default => 3,
            };

            return new Paginator(new Collection([
                new SearchResultData(
                    title: 'Laravel Search',
                    url: '/laravel-search',
                    excerpt: 'Search result content',
                    type: 'page',
                ),
            ]), $total, $perPage, $page);
        }

        public function highlight(string $text, string $query): string
        {
            return e($text);
        }
    });

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, [
        'q' => 'Laravel',
        'type' => ['page'],
    ]);
    $view = (new SearchController)($request);
    $html = $view->render();

    expect($view->getData()['facetGroups'])->toHaveCount(2)
        ->and($html)->toContain('Search filters')
        ->and($html)->toContain('Type')
        ->and($html)->toContain('Page')
        ->and($html)->toContain('Article')
        ->and($html)->toContain('Source')
        ->and($html)->toContain('Pages')
        ->and($html)->toContain('Articles')
        ->and($html)->toContain('aria-current="true"')
        ->and($html)->not()->toContain('capell-search');
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

/**
 * @return array<string, mixed>
 */
function searchControllerJsonPayload(JsonResponse $response): array
{
    $payload = $response->getData(true);

    return is_array($payload) ? searchControllerStringKeyedArray($payload) : [];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function searchControllerPayloadArray(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    return is_array($value) ? searchControllerStringKeyedArray($value) : [];
}

/**
 * @param  array<string, mixed>  $payload
 * @return list<array<string, mixed>>
 */
function searchControllerPayloadResults(array $payload): array
{
    return searchControllerListOfStringKeyedArrays($payload['results'] ?? []);
}

/**
 * @param  array<string, mixed>  $payload
 * @return list<array<string, mixed>>
 */
function searchControllerPayloadSuggestions(array $payload): array
{
    return searchControllerListOfStringKeyedArrays($payload['querySuggestions'] ?? []);
}

/**
 * @return list<array<string, mixed>>
 */
function searchControllerListOfStringKeyedArrays(mixed $items): array
{
    if (! is_array($items)) {
        return [];
    }

    $results = [];

    foreach ($items as $item) {
        if (is_array($item)) {
            $results[] = searchControllerStringKeyedArray($item);
        }
    }

    return $results;
}

/**
 * @param  array<mixed>  $values
 * @return array<string, mixed>
 */
function searchControllerStringKeyedArray(array $values): array
{
    $result = [];

    foreach ($values as $key => $value) {
        if (is_string($key)) {
            $result[$key] = $value;
        }
    }

    return $result;
}
