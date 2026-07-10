<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchPageViewData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static SearchPageViewData run(Request $request)
 */
final readonly class BuildSearchPageViewDataAction
{
    use AsAction;

    public function __construct(private Search $search) {}

    public function handle(Request $request): SearchPageViewData
    {
        $query = (string) $request->query('q', '');
        $data = $this->searchRequestData(
            query: $query,
            page: max(1, (int) $request->query('page', 1)),
            perPage: $this->resultsPerPage(),
            request: $request,
        );

        $results = RunSearchAction::run($data)->withPath($request->url());
        $facetGroups = BuildSearchFacetGroupsAction::run(
            request: $request,
            query: $query,
            filters: $data->filters ?? new SearchFilterData,
            siteId: $data->siteId,
            languageId: $data->languageId,
        );

        if (CanCollectSearchAnalyticsAction::run($request) && $data->siteId !== null) {
            RecordSearchAction::dispatchAfterResponse(
                $data,
                $results->total(),
                CreateSearchVisitorIdentityAction::run($request, $data->siteId),
            );
        }

        return new SearchPageViewData(
            query: $query,
            results: $results,
            highlightedResults: $this->highlightedResults($results, $query),
            facetGroups: $facetGroups,
            clickTrackingToken: GenerateSearchClickTokenAction::run($data),
        );
    }

    private function resultsPerPage(): int
    {
        return (int) ResolveSearchSettingAction::run(
            'results_per_page',
            'capell-search.results_per_page',
            10,
        );
    }

    private function searchRequestData(string $query, int $page, int $perPage, Request $request): SearchRequestData
    {
        $site = $request->attributes->get('site');
        $language = $request->attributes->get('language');

        return new SearchRequestData(
            query: $query,
            page: $page,
            perPage: $perPage,
            siteId: is_object($site) ? (int) data_get($site, 'id') : null,
            languageId: is_object($language) ? (int) data_get($language, 'id') : null,
            filters: NormalizeSearchFiltersAction::run($request),
        );
    }

    /**
     * @param  LengthAwarePaginator<int, SearchResultData>  $results
     * @return Collection<int, array{title: string, excerpt: string}>
     */
    private function highlightedResults(LengthAwarePaginator $results, string $query): Collection
    {
        return collect($results->items())
            ->map(fn (SearchResultData $result): array => [
                'title' => $this->search->highlight($result->title, $query),
                'excerpt' => $this->search->highlight($result->excerpt, $query),
            ])
            ->values();
    }
}
