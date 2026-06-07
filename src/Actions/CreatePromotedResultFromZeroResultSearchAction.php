<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Settings\SearchSettings;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

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
            return $this->settings()->promoted_results;
        }

        $settings = $this->settings();
        $promotedResults = $this->withoutDuplicatePromotion($settings->promoted_results, $query, $url);
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

        return $settings->promoted_results;
    }

    /**
     * @param  array<int, mixed>  $promotedResults
     * @return list<array<string, mixed>>
     */
    private function withoutDuplicatePromotion(array $promotedResults, string $query, string $url): array
    {
        return array_values(array_filter($promotedResults, static function (mixed $promotion) use ($query, $url): bool {
            if (! is_array($promotion)) {
                return false;
            }

            $promotionUrl = $promotion['url'] ?? null;
            $queries = $promotion['queries'] ?? $promotion['query'] ?? [];
            $queries = is_array($queries) ? $queries : [$queries];
            $normalizedQueries = array_map(
                static fn (mixed $item): string => is_scalar($item) ? NormalizeSearchQueryAction::run((string) $item) : '',
                $queries,
            );

            return $promotionUrl !== $url || ! in_array($query, $normalizedQueries, true);
        }));
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
