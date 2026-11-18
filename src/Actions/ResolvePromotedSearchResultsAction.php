<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\PromotedSearchResultData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<PromotedSearchResultData> run(string $normalizedQuery, ?int $siteId = null, ?int $languageId = null)
 */
final class ResolvePromotedSearchResultsAction
{
    use AsFake;
    use AsObject;

    /**
     * A promotion with `site_ids` or `language_ids` applies only there; without them it applies everywhere.
     *
     * @param  array<array-key, mixed>  $configuredPromotion
     */
    public static function appliesTo(array $configuredPromotion, ?int $siteId, ?int $languageId): bool
    {
        foreach (['site_ids' => $siteId, 'language_ids' => $languageId] as $key => $current) {
            $allowed = $configuredPromotion[$key] ?? [];

            if (is_array($allowed) && $allowed !== [] && ($current === null || ! in_array($current, array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $allowed), true))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<PromotedSearchResultData>
     */
    public function handle(string $normalizedQuery, ?int $siteId = null, ?int $languageId = null): array
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

            if (! self::appliesTo($configuredPromotion, $siteId, $languageId)) {
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
