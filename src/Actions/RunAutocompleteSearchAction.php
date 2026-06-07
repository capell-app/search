<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\AutocompleteQuerySuggestionData;
use Capell\Search\Data\AutocompleteSearchResponseData;
use Capell\Search\Data\AutocompleteSearchResultData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchQueryMetadataData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RunAutocompleteSearchAction
{
    use AsAction;

    public function __construct(
        private NormalizeSearchFiltersAction $normalizeSearchFilters,
        private Search $search,
    ) {}

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
        $correctedQuery = ResolveCorrectedSearchQueryAction::run((string) $normalizedQuery);
        $metadata = new SearchQueryMetadataData(
            original: $query,
            normalized: (string) $normalizedQuery,
            corrected: $correctedQuery,
            expanded: $normalizedQuery === '' ? [] : ResolveExpandedSearchQueriesAction::run((string) $normalizedQuery),
            filters: $filters,
        );

        if ($normalizedQuery === '' || mb_strlen((string) $normalizedQuery) < $minimumLength) {
            return new AutocompleteSearchResponseData(
                query: $query,
                minimumLength: $minimumLength,
                results: [],
                querySuggestions: [],
                allResultsUrl: $this->allResultsUrl($query, $filters),
                metadata: $metadata,
            );
        }

        $limit = max(1, min(20, (int) config('capell-search.autocomplete.limit', 6)));
        $site = $request->attributes->get('site');
        $language = $request->attributes->get('language');
        $siteId = is_object($site) ? (int) data_get($site, 'id') : null;
        $languageId = is_object($language) ? (int) data_get($language, 'id') : null;

        $results = $this->search->search(
            query: (string) $normalizedQuery,
            perPage: $limit,
            page: 1,
            siteId: $siteId,
            languageId: $languageId,
            filters: $filters,
        );

        /** @var list<SearchResultData> $items */
        $items = $results->items();

        return new AutocompleteSearchResponseData(
            query: $query,
            minimumLength: $minimumLength,
            results: array_values(collect($items)
                ->take($limit)
                ->map(fn (SearchResultData $result): AutocompleteSearchResultData => new AutocompleteSearchResultData(
                    title: $result->title,
                    url: $result->url,
                    excerpt: $result->excerpt,
                    type: $result->type,
                    typeLabel: $result->typeLabel ?? ResolveSearchResultTypeLabelAction::run($result->type),
                    score: $result->score,
                ))
                ->values()
                ->all()),
            querySuggestions: $this->querySuggestions(
                query: (string) $normalizedQuery,
                correctedQuery: $correctedQuery,
                limit: $limit,
                siteId: $siteId,
                languageId: $languageId,
            ),
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

    /**
     * @return list<AutocompleteQuerySuggestionData>
     */
    private function querySuggestions(
        string $query,
        ?string $correctedQuery,
        int $limit,
        ?int $siteId,
        ?int $languageId,
    ): array {
        $suggestions = BuildAutocompleteQuerySuggestionsAction::run(
            query: $query,
            limit: $limit,
            siteId: $siteId,
            languageId: $languageId,
        );

        if ($correctedQuery === null || $correctedQuery === $query) {
            return $suggestions;
        }

        return array_values(array_filter(
            $suggestions,
            static fn (AutocompleteQuerySuggestionData $suggestion): bool => $suggestion->query !== $correctedQuery,
        ));
    }
}
