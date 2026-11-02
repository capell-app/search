<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Support\ExpandedSearchPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Paginator<int, SearchResultData> run(SearchRequestData $data)
 */
final readonly class RunSearchAction
{
    use AsFake;
    use AsObject;

    public function __construct(private Search $search) {}

    /**
     * @return Paginator<int, SearchResultData>
     */
    public function handle(SearchRequestData $data): Paginator
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($data->query);
        $minimumLength = $this->integerSetting(
            'minimum_query_length',
            'capell-search.minimum_query_length',
            2,
        );

        if ($normalizedQuery === '' || mb_strlen((string) $normalizedQuery) < $minimumLength) {
            /** @var list<SearchResultData> $items */
            $items = [];

            return new Paginator($items, 0, $data->perPage, $data->page);
        }

        $queries = ResolveExpandedSearchQueriesAction::run($normalizedQuery);
        $queries = array_slice($queries, 0, max(1, min(10, $this->integerConfig('capell-search.query_expansion.max_queries', 3))));

        if (count($queries) > 1) {
            return $this->expandedResults($queries, $data, $normalizedQuery);
        }

        $results = $this->searchForQueries(
            queries: $queries,
            perPage: $data->perPage,
            page: $data->page,
            siteId: $data->siteId,
            languageId: $data->languageId,
            filters: $data->filters,
        );

        return ApplySearchResultEnhancementsAction::run($results, $normalizedQuery, $data->siteId, $data->languageId);
    }

    /**
     * @param  list<string>  $queries
     * @return Paginator<int, SearchResultData>
     */
    private function expandedResults(array $queries, SearchRequestData $data, string $normalizedQuery): Paginator
    {
        $items = new Collection;
        $truncated = false;

        foreach ($queries as $query) {
            $results = $this->search->search(
                query: $query,
                perPage: 100,
                page: 1,
                siteId: $data->siteId,
                languageId: $data->languageId,
                filters: $data->filters,
            );

            $truncated = $truncated || $results->total() > count($results->items());
            $items = $items->merge($results->items());
        }

        $items = $items
            ->map(static fn (SearchResultData $result): ?SearchResultData => SanitizeSearchResultAction::run($result))
            ->filter(static fn (?SearchResultData $result): bool => $result instanceof SearchResultData)
            ->unique(static fn (SearchResultData $result): string => $result->url)
            ->values();

        // Rank and promote the entire bounded set before slicing, so a URL cannot
        // move between pages merely because it was promoted on the first page.
        $ranked = ApplySearchResultEnhancementsAction::run(
            new Paginator($items, $items->count(), max(1, $items->count()), 1),
            $normalizedQuery,
            $data->siteId,
            $data->languageId,
        );
        $items = (new Collection($ranked->items()))
            ->unique(static fn (SearchResultData $result): string => $result->url)
            ->sort(static fn (SearchResultData $left, SearchResultData $right): int => ($right->promoted <=> $left->promoted)
                ?: ($right->score <=> $left->score)
                ?: strcmp($left->url, $right->url))
            ->values();
        $perPage = max(1, $data->perPage);
        $page = max(1, $data->page);

        return new ExpandedSearchPaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, $truncated);
    }

    private function integerSetting(string $settingKey, string $configKey, int $fallback): int
    {
        $value = ResolveSearchSettingAction::run($settingKey, $configKey, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function integerConfig(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /**
     * @param  list<string>  $queries
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    private function searchForQueries(
        array $queries,
        int $perPage,
        int $page,
        ?int $siteId,
        ?int $languageId,
        ?SearchFilterData $filters,
    ): LengthAwarePaginator {
        $primaryQuery = $queries[0] ?? '';

        return $this->search->search(
            query: $primaryQuery,
            perPage: $perPage,
            page: $page,
            siteId: $siteId,
            languageId: $languageId,
            filters: $filters,
        );
    }
}
