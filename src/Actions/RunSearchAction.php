<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RunSearchAction
{
    use AsAction;

    public function __construct(private Search $search) {}

    /**
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    public function handle(SearchRequestData $data): LengthAwarePaginator
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($data->query);
        $minimumLength = (int) ResolveSearchSettingAction::run(
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

        $results = $this->searchForQueries(
            queries: $queries,
            perPage: $data->perPage,
            page: $data->page,
            siteId: $data->siteId,
            languageId: $data->languageId,
        );

        return ApplySearchResultEnhancementsAction::run($results, $normalizedQuery);
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
    ): LengthAwarePaginator {
        $primaryQuery = $queries[0] ?? '';

        if (count($queries) <= 1) {
            return $this->search->search(
                query: $primaryQuery,
                perPage: $perPage,
                page: $page,
                siteId: $siteId,
                languageId: $languageId,
            );
        }

        $fetchLimit = max($perPage, $perPage * max(1, $page));
        $items = new Collection;
        $total = 0;

        foreach ($queries as $query) {
            $results = $this->search->search(
                query: $query,
                perPage: $fetchLimit,
                page: 1,
                siteId: $siteId,
                languageId: $languageId,
            );

            $total += $results->total();
            $items = $items->merge($results->items());
        }

        $items = $items
            ->unique(static fn (SearchResultData $result): string => $result->url)
            ->values();

        return new Paginator(
            items: $items->forPage($page, $perPage)->values(),
            total: min($total, $items->count()),
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
