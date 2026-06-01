<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\PromotedSearchResultData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final class ApplySearchResultEnhancementsAction
{
    use AsAction;

    /**
     * @param  LengthAwarePaginator<int, SearchResultData>  $results
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    public function handle(LengthAwarePaginator $results, string $normalizedQuery): LengthAwarePaginator
    {
        $promotedResults = (new ResolvePromotedSearchResultsAction)->handle($normalizedQuery);
        $sourceWeights = $this->sourceWeights();

        if ($promotedResults === [] && $sourceWeights === []) {
            return $results;
        }

        $promotedUrls = (new Collection($promotedResults))
            ->map(static fn (PromotedSearchResultData $promotion): string => $promotion->toSearchResult()->url)
            ->all();

        $items = (new Collection($promotedResults))
            ->map(static fn (PromotedSearchResultData $promotion): SearchResultData => $promotion->toSearchResult())
            ->merge((new Collection($results->items()))->reject(
                static fn (SearchResultData $result): bool => in_array($result->url, $promotedUrls, true),
            ))
            ->map(fn (SearchResultData $result): SearchResultData => $this->applySourceWeight($result, $sourceWeights));

        if ($sourceWeights !== []) {
            $items = $this->sortByWeightedScore($items, count($promotedResults));
        }

        return new Paginator(
            items: $items->values(),
            total: max($results->total(), $items->count()),
            perPage: $results->perPage(),
            currentPage: $results->currentPage(),
            options: [
                'path' => $results->path(),
                'pageName' => $results instanceof Paginator ? $results->getPageName() : 'page',
            ],
        );
    }

    /**
     * @return array<string, float>
     */
    private function sourceWeights(): array
    {
        $configuredWeights = config('capell-search.source_weights', []);

        if (! is_array($configuredWeights)) {
            return [];
        }

        $weights = [];

        foreach ($configuredWeights as $source => $weight) {
            if (! is_string($source)) {
                continue;
            }

            if (! is_numeric($weight)) {
                continue;
            }

            $normalizedSource = trim($source);

            if ($normalizedSource !== '') {
                $weights[$normalizedSource] = max(0.0, (float) $weight);
            }
        }

        return $weights;
    }

    /**
     * @param  array<string, float>  $sourceWeights
     */
    private function applySourceWeight(SearchResultData $result, array $sourceWeights): SearchResultData
    {
        $weight = $sourceWeights[$result->type] ?? 1.0;

        if ($weight === 1.0) {
            return $result;
        }

        return new SearchResultData(
            title: $result->title,
            url: $result->url,
            excerpt: $result->excerpt,
            type: $result->type,
            score: $result->score * $weight,
        );
    }

    /**
     * @param  Collection<int, SearchResultData>  $items
     * @return Collection<int, SearchResultData>
     */
    private function sortByWeightedScore(Collection $items, int $promotedCount): Collection
    {
        return $items
            ->values()
            ->sortByDesc(static fn (SearchResultData $result, int $index): array => [
                $index < $promotedCount ? 1 : 0,
                $result->score,
                -$index,
            ]);
    }
}
