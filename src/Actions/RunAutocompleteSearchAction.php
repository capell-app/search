<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\AutocompleteSearchResponseData;
use Capell\Search\Data\AutocompleteSearchResultData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchQueryMetadataData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RunAutocompleteSearchAction
{
    use AsAction;

    public function __construct(private NormalizeSearchFiltersAction $normalizeSearchFilters) {}

    public function handle(Request $request): AutocompleteSearchResponseData
    {
        $query = (string) $request->query('q', '');
        $normalizedQuery = NormalizeSearchQueryAction::run($query);
        $minimumLength = (int) ResolveSearchSettingAction::run(
            'minimum_query_length',
            'capell-search.minimum_query_length',
            2,
        );
        $filters = $this->normalizeSearchFilters->handle($request);
        $metadata = new SearchQueryMetadataData(
            original: $query,
            normalized: (string) $normalizedQuery,
            expanded: $normalizedQuery === '' ? [] : ResolveExpandedSearchQueriesAction::run((string) $normalizedQuery),
            filters: $filters,
        );

        if ($normalizedQuery === '' || mb_strlen((string) $normalizedQuery) < $minimumLength) {
            return new AutocompleteSearchResponseData(
                query: $query,
                minimumLength: $minimumLength,
                results: [],
                allResultsUrl: $this->allResultsUrl($query, $filters),
                metadata: $metadata,
            );
        }

        $limit = max(1, min(20, (int) config('capell-search.autocomplete.limit', 6)));
        $site = $request->attributes->get('site');
        $language = $request->attributes->get('language');

        $results = RunSearchAction::run(new SearchRequestData(
            query: $query,
            page: 1,
            perPage: $limit,
            siteId: is_object($site) ? (int) data_get($site, 'id') : null,
            languageId: is_object($language) ? (int) data_get($language, 'id') : null,
            filters: $filters,
        ));

        /** @var list<SearchResultData> $items */
        $items = $results->items();

        return new AutocompleteSearchResponseData(
            query: $query,
            minimumLength: $minimumLength,
            results: array_values(collect($items)
                ->take($limit)
                ->map(static fn (SearchResultData $result): AutocompleteSearchResultData => AutocompleteSearchResultData::fromSearchResult($result))
                ->values()
                ->all()),
            allResultsUrl: $this->allResultsUrl($query, $filters),
            metadata: $metadata,
        );
    }

    private function allResultsUrl(string $query, SearchFilterData $filters): string
    {
        $parameters = ['q' => $query];

        if ($filters->types !== []) {
            $parameters['type'] = $filters->types;
        }

        if ($filters->sourceKeys !== []) {
            $parameters['source'] = $filters->sourceKeys;
        }

        return route('capell-frontend.search', $parameters);
    }
}
