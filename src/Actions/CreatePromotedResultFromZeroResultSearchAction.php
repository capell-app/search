<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Settings\SearchSettings;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * @method static list<array<string, mixed>> run(string $zeroResultQuery, string $title, string $url, string $excerpt = '', string $type = 'page', float $score = 1000.0)
 */
final class CreatePromotedResultFromZeroResultSearchAction
{
    use AsAction;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(
        string $zeroResultQuery,
        string $title,
        string $url,
        string $excerpt = '',
        string $type = 'page',
        float $score = 1000.0,
    ): array {
        $query = NormalizeSearchQueryAction::run($zeroResultQuery);
        $title = trim($title);
        $url = trim($url);

        if ($query === '' || $title === '' || $url === '') {
            return $this->promotedResults($this->settings()->promoted_results);
        }

        $settings = $this->settings();
        $promotedResults = $this->withoutDuplicatePromotion($this->promotedResults($settings->promoted_results), $query, $url);
        $promotedResults[] = [
            'queries' => [$query],
            'title' => $title,
            'url' => $url,
            'excerpt' => trim($excerpt),
            'type' => trim($type) !== '' ? trim($type) : 'page',
            'score' => $score,
        ];

        $settings->promoted_results = array_values($promotedResults);
        $this->save($settings);

        return $this->promotedResults($settings->promoted_results);
    }

    /**
     * @param  list<array<string, mixed>>  $promotedResults
     * @return list<array<string, mixed>>
     */
    private function withoutDuplicatePromotion(array $promotedResults, string $query, string $url): array
    {
        $filtered = [];

        foreach ($promotedResults as $promotion) {
            $promotionUrl = $promotion['url'] ?? null;
            $queries = $promotion['queries'] ?? $promotion['query'] ?? [];
            $queries = is_array($queries) ? $queries : [$queries];

            $normalizedQueries = array_map(
                static fn (mixed $item): string => is_scalar($item) ? NormalizeSearchQueryAction::run((string) $item) : '',
                $queries,
            );

            if ($promotionUrl !== $url || ! in_array($query, $normalizedQueries, true)) {
                $filtered[] = $promotion;
            }
        }

        return $filtered;
    }

    /**
     * @param  array<mixed>  $promotedResults
     * @return list<array<string, mixed>>
     */
    private function promotedResults(array $promotedResults): array
    {
        $results = [];

        foreach ($promotedResults as $promotion) {
            if (! is_array($promotion)) {
                continue;
            }

            $results[] = $this->stringKeyedArray($promotion);
        }

        return $results;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function settings(): SearchSettings
    {
        return resolve(SearchSettings::class);
    }

    private function save(SearchSettings $settings): void
    {
        try {
            $settings->save();
        } catch (Throwable) {
            //
        }
    }
}
