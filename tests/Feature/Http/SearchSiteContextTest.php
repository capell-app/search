<?php

declare(strict_types=1);

use Capell\Core\Contracts\SiteAccessPolicyProvider;
use Capell\Core\Data\SiteAccessContextData;
use Capell\Core\Data\SiteAccessPolicyData;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\SiteAccess\SiteAccessPolicyRegistry;
use Capell\Search\Actions\GenerateSearchClickTokenAction;
use Capell\Search\Actions\HashSearchRetentionValueAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Models\SearchLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

it('scopes the native autocomplete route to the requested public domain', function (): void {
    $domain = SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null]);
    $otherDomain = SiteDomain::factory()->createOne(['domain' => 'other.example', 'scheme' => 'https', 'path' => null]);
    $backend = new class implements Search
    {
        public ?int $siteId = null;

        public ?int $languageId = null;

        #[Override]
        public function search(string $query, int $perPage = 10, int $page = 1, ?int $siteId = null, ?int $languageId = null, ?SearchFilterData $filters = null): LengthAwarePaginator
        {
            $this->siteId = $siteId;
            $this->languageId = $languageId;

            return new Paginator(new Collection([new SearchResultData(title: 'Scoped result', url: '/scoped-result', excerpt: '', type: 'page')]), 1, $perPage, $page);
        }

        #[Override]
        public function highlight(string $text, string $query): string
        {
            return e($text);
        }
    };
    app()->instance(Search::class, $backend);

    $this->getJson('https://search.example/search/autocomplete?q=Capell&site_id=' . $otherDomain->site_id)
        ->assertOk()
        ->assertJsonPath('results.0.title', 'Scoped result');

    expect($backend->siteId)->toBe($domain->site_id)
        ->and($backend->languageId)->toBe($domain->language_id);
});

it('refuses native autocomplete on unknown or disabled public domains', function (bool $disabled): void {
    SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null, 'status' => ! $disabled]);
    $host = $disabled ? 'search.example' : 'unknown.example';

    $this->getJson('https://' . $host . '/search/autocomplete?q=Capell')->assertNotFound();
})->with([false, true]);

it('refuses autocomplete when the resolved site has an active access policy', function (bool $configurationAvailable): void {
    SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null]);
    resolve(SiteAccessPolicyRegistry::class)->register(new readonly class($configurationAvailable) implements SiteAccessPolicyProvider
    {
        public function __construct(private bool $configurationAvailable) {}

        #[Override]
        public function key(): string
        {
            return 'search-test-policy';
        }

        #[Override]
        public function resolve(SiteAccessContextData $context): SiteAccessPolicyData
        {
            return new SiteAccessPolicyData(active: true, configurationAvailable: $this->configurationAvailable);
        }
    });
    $this->mock(Search::class)->shouldNotReceive('search');

    $this->getJson('https://search.example/search/autocomplete?q=Capell')->assertNotFound();
})->with([true, false]);

it('records a signed click only on its resolved public site', function (): void {
    $domain = SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null]);
    SiteDomain::factory()->createOne(['domain' => 'other.example', 'scheme' => 'https', 'path' => null]);
    $data = new SearchRequestData(query: 'Capell', siteId: $domain->site_id, languageId: $domain->language_id);
    $log = SearchLog::query()->create([
        'site_id' => $domain->site_id,
        'language_id' => $domain->language_id,
        'query' => 'Capell',
        'normalized_query' => HashSearchRetentionValueAction::run('capell'),
        'normalized_query_hash' => HashSearchRetentionValueAction::run('capell'),
        'results_count' => 1,
        'searched_at' => now(),
    ]);
    $payload = ['query' => 'Capell', 'url' => '/scoped-result', 'token' => GenerateSearchClickTokenAction::run($data, '/scoped-result')];

    $this->post('https://other.example/search/click', $payload)->assertNoContent();
    expect($log->refresh()->clicked_result_url)->toBeNull();

    $this->post('https://search.example/search/click', $payload)->assertNoContent();
    expect($log->refresh()->clicked_result_url)->toBe('/scoped-result');
});

it('refuses autocomplete for a disabled site with an enabled domain', function (): void {
    $domain = SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null]);
    $domain->site->update(['status' => false]);

    $this->getJson('https://search.example/search/autocomplete?q=Capell')->assertNotFound();
});

it('throttles autocomplete before resolving the public site access policy again', function (): void {
    config()->set('capell-search.autocomplete.rate_limit.per_minute', 1);
    SiteDomain::factory()->createOne(['domain' => 'search.example', 'scheme' => 'https', 'path' => null]);
    $provider = new class implements SiteAccessPolicyProvider
    {
        public int $calls = 0;

        #[Override]
        public function key(): string
        {
            return 'search-throttle-test';
        }

        #[Override]
        public function resolve(SiteAccessContextData $context): null
        {
            $this->calls++;

            return null;
        }
    };
    resolve(SiteAccessPolicyRegistry::class)->register($provider);

    $this->getJson('https://search.example/search/autocomplete?q=')->assertOk();
    $this->getJson('https://search.example/search/autocomplete?q=')->assertTooManyRequests();
    expect($provider->calls)->toBe(1);
});
