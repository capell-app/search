<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\AutocompleteQuerySuggestionData;
use Capell\Search\Models\SearchLog;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static list<AutocompleteQuerySuggestionData> run(string $query, int $limit = 5, ?int $siteId = null, ?int $languageId = null)
 */
final class BuildAutocompleteQuerySuggestionsAction
{
    use AsAction;

    /**
     * @return list<AutocompleteQuerySuggestionData>
     */
    public function handle(
        string $query,
        int $limit = 5,
        ?int $siteId = null,
        ?int $languageId = null,
    ): array {
        $normalizedQuery = NormalizeSearchQueryAction::run($query);
        $limit = max(0, min(10, $limit));

        if ($normalizedQuery === '' || $limit === 0) {
            return [];
        }

        $model = new SearchLog;

        if (! Schema::hasTable($model->getTable())) {
            return [];
        }

        $queryBuilder = SearchLog::query()
            ->selectRaw('normalized_query, count(*) as searches')
            ->where('normalized_query', 'like', $this->escapeLike($normalizedQuery) . '%')
            ->where('normalized_query', '!=', $normalizedQuery)
            ->groupBy('normalized_query')
            ->orderByDesc('searches')
            ->orderBy('normalized_query')
            ->limit($limit);

        if ($siteId !== null) {
            $queryBuilder->where('site_id', $siteId);
        }

        if ($languageId !== null) {
            $queryBuilder->where('language_id', $languageId);
        }

        return array_values($queryBuilder
            ->get()
            ->map(fn (object $row): AutocompleteQuerySuggestionData => new AutocompleteQuerySuggestionData(
                query: (string) $row->normalized_query,
                searches: (int) $row->searches,
                url: route('capell-frontend.search', ['q' => (string) $row->normalized_query]),
            ))
            ->values()
            ->all());
    }

    private function escapeLike(string $query): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    }
}
