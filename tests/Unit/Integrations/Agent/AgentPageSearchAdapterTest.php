<?php

declare(strict_types=1);

use Capell\Core\Data\Agent\AgentSearchResultData;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Integrations\Agent\AgentPageSearchAdapter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

test('agent adapter projects only public page results and preserves search scope', function (): void {
    $call = new stdClass;
    $search = new class($call) implements Search
    {
        public function __construct(private stdClass $call) {}

        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $this->call->query = $query;
            $this->call->perPage = $perPage;
            $this->call->page = $page;
            $this->call->siteId = $siteId;
            $this->call->languageId = $languageId;
            $this->call->filters = $filters;

            return new Paginator([
                new SearchResultData(
                    title: '<strong>Public page</strong>',
                    url: '/public-page',
                    excerpt: '<p>Read &amp; learn <em>more</em>.</p>',
                    type: 'page',
                ),
                new SearchResultData(
                    title: 'Article',
                    url: '/article',
                    excerpt: 'Must stay outside the page boundary.',
                    type: 'article',
                ),
            ], total: 2, perPage: $perPage, currentPage: $page, options: ['path' => '/agent/v1/search']);
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    };

    $results = new AgentPageSearchAdapter($search, perPage: 7)->search(
        query: ' CMS ',
        siteId: 42,
        languageId: 3,
        page: 2,
    );
    $first = $results->items()[0] ?? null;

    expect($call->query)->toBe(' CMS ')
        ->and($call->perPage)->toBe(7)
        ->and($call->page)->toBe(2)
        ->and($call->siteId)->toBe(42)
        ->and($call->languageId)->toBe(3)
        ->and($call->filters)->toBeInstanceOf(SearchFilterData::class)
        ->and($call->filters->types)->toBe(['page'])
        ->and($results->total())->toBe(2)
        ->and($results->path())->toBe('/agent/v1/search')
        ->and($first)->toBeInstanceOf(AgentSearchResultData::class)
        ->and($first?->url)->toBe('/public-page')
        ->and($first?->title)->toBe('Public page')
        ->and($first?->snippet)->toBe('Read & learn more.');
});
