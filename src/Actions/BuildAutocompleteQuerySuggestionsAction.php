<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\AutocompleteQuerySuggestionData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Suggests curated terms only (synonyms, corrections and promoted-result queries
 * an editor configured). Visitors' logged queries are never suggested: they are
 * personal data and the log stores only an HMAC of them.
 *
 * @method static list<AutocompleteQuerySuggestionData> run(string $query, int $limit = 5, ?int $siteId = null, ?int $languageId = null)
 */
final class BuildAutocompleteQuerySuggestionsAction
{
    use AsFake;
    use AsObject;

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

        $suggestions = [];

        foreach ($this->curatedTerms($siteId, $languageId) as $term) {
            if ($term === $normalizedQuery || ! str_starts_with($term, $normalizedQuery)) {
                continue;
            }

            $suggestions[] = new AutocompleteQuerySuggestionData(
                query: $term,
                searches: 0,
                url: route('capell-frontend.search', ['q' => $term], false),
            );

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @return list<string>
     */
    private function curatedTerms(?int $siteId, ?int $languageId): array
    {
        $raw = [];

        $synonyms = ResolveSiteScopedSynonymsAction::run($siteId);

        foreach ($synonyms as $key => $aliases) {
            $raw[] = $key;
            $raw = [...$raw, ...(is_array($aliases) ? $aliases : [$aliases])];
        }

        $corrections = ResolveSearchSettingAction::run('typo_corrections', 'capell-search.typo_corrections', []);

        if (is_array($corrections)) {
            $raw = [...$raw, ...array_values($corrections)];
        }

        $promoted = ResolveSearchSettingAction::run('promoted_results', 'capell-search.promoted_results', []);

        if (is_array($promoted)) {
            foreach ($promoted as $promotion) {
                if (is_array($promotion) && is_array($promotion['queries'] ?? null) && ResolvePromotedSearchResultsAction::appliesTo($promotion, $siteId, $languageId)) {
                    $raw = [...$raw, ...$promotion['queries']];
                }
            }
        }

        $terms = [];

        foreach ($raw as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $term = NormalizeSearchQueryAction::run((string) $value);

            if ($term !== '') {
                $terms[$term] = $term;
            }
        }

        $terms = array_values($terms);
        sort($terms);

        return $terms;
    }
}
