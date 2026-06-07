<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\PromotedSearchResultData;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolvePromotedSearchResultsAction
{
    use AsAction;

    /**
     * @return list<PromotedSearchResultData>
     */
    public function handle(string $normalizedQuery): array
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($normalizedQuery);
        $configuredPromotions = ResolveSearchSettingAction::run('promoted_results', 'capell-search.promoted_results', []);

        if ($normalizedQuery === '' || ! is_array($configuredPromotions)) {
            return [];
        }

        $promotions = [];

        foreach ($configuredPromotions as $configuredPromotion) {
            if (! is_array($configuredPromotion)) {
                continue;
            }

            $promotion = $this->promotionFromConfig($configuredPromotion);
            if (! $promotion instanceof PromotedSearchResultData) {
                continue;
            }

            if (! in_array($normalizedQuery, $promotion->queries, true)) {
                continue;
            }

            $promotions[] = $promotion;
        }

        return $promotions;
    }

    /**
     * @param  array<string, mixed>  $configuredPromotion
     */
    private function promotionFromConfig(array $configuredPromotion): ?PromotedSearchResultData
    {
        $title = $configuredPromotion['title'] ?? null;
        $url = $configuredPromotion['url'] ?? null;

        if (! is_string($title) || trim($title) === '' || ! is_string($url) || trim($url) === '') {
            return null;
        }

        $queries = $this->queriesFromConfig($configuredPromotion['queries'] ?? $configuredPromotion['query'] ?? []);

        if ($queries === []) {
            return null;
        }

        return new PromotedSearchResultData(
            queries: $queries,
            title: $title,
            url: $url,
            excerpt: is_string($configuredPromotion['excerpt'] ?? null) ? $configuredPromotion['excerpt'] : '',
            type: is_string($configuredPromotion['type'] ?? null) ? $configuredPromotion['type'] : 'page',
            score: is_numeric($configuredPromotion['score'] ?? null) ? (float) $configuredPromotion['score'] : 1000.0,
        );
    }

    /**
     * @return list<string>
     */
    private function queriesFromConfig(mixed $configuredQueries): array
    {
        if (is_string($configuredQueries) || is_numeric($configuredQueries)) {
            $configuredQueries = [$configuredQueries];
        }

        if (! is_array($configuredQueries)) {
            return [];
        }

        $queries = [];

        foreach ($configuredQueries as $configuredQuery) {
            if (! is_string($configuredQuery) && ! is_numeric($configuredQuery)) {
                continue;
            }

            $query = NormalizeSearchQueryAction::run((string) $configuredQuery);

            if ($query !== '') {
                $queries[] = $query;
            }
        }

        return array_values(array_unique($queries));
    }
}
