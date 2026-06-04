<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\PromotedSearchResultData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Models\SearchLog;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
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

        $promotedUrls = (new Collection($promotedResults))
            ->map(static fn (PromotedSearchResultData $promotion): string => $promotion->toSearchResult()->url)
            ->all();

        $items = (new Collection($promotedResults))
            ->map(static fn (PromotedSearchResultData $promotion): SearchResultData => $promotion->toSearchResult())
            ->merge((new Collection($results->items()))->reject(
                static fn (SearchResultData $result): bool => in_array($result->url, $promotedUrls, true),
            ))
            ->map(fn (SearchResultData $result): ?SearchResultData => SanitizeSearchResultAction::run($result))
            ->filter(static fn (?SearchResultData $result): bool => $result instanceof SearchResultData)
            ->map(fn (SearchResultData $result): SearchResultData => $this->applyTypeLabel($result))
            ->map(fn (SearchResultData $result): SearchResultData => $this->applySourceWeight($result, $sourceWeights))
            ->map(fn (SearchResultData $result): SearchResultData => $this->applyExactMatchBoost($result, $normalizedQuery))
            ->map(fn (SearchResultData $result): SearchResultData => $this->applyRecencyBoost($result))
            ->map(fn (SearchResultData $result): SearchResultData => $this->applyClickBoost($result, $this->clickCounts()));

        $items = $this->sortByWeightedScore($items);

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

    private function applyTypeLabel(SearchResultData $result): SearchResultData
    {
        $label = $this->typeLabels()[$result->type] ?? str($result->type)->replace(['_', '-'], ' ')->headline()->toString();

        return new SearchResultData(
            title: $result->title,
            url: $result->url,
            excerpt: $result->excerpt,
            type: $result->type,
            score: $result->score,
            typeLabel: $label,
            sourceKey: $result->sourceKey,
            updatedAt: $result->updatedAt,
            meta: $result->meta,
            promoted: $result->promoted,
        );
    }

    /**
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        $configuredLabels = config('capell-search.type_labels', []);

        if (! is_array($configuredLabels)) {
            return [];
        }

        $labels = [];

        foreach ($configuredLabels as $type => $label) {
            if (! is_string($type)) {
                continue;
            }

            if (! is_string($label)) {
                continue;
            }

            $normalizedType = trim($type);
            $normalizedLabel = trim($label);

            if ($normalizedType !== '' && $normalizedLabel !== '') {
                $labels[$normalizedType] = $normalizedLabel;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, float>  $sourceWeights
     */
    private function applySourceWeight(SearchResultData $result, array $sourceWeights): SearchResultData
    {
        $weight = $sourceWeights[$result->sourceKey ?? ''] ?? $sourceWeights[$result->type] ?? 1.0;

        if ($weight === 1.0) {
            return $result;
        }

        return $this->withScore($result, $result->score * $weight);
    }

    private function applyExactMatchBoost(SearchResultData $result, string $normalizedQuery): SearchResultData
    {
        $needle = str($normalizedQuery)->lower()->toString();
        $title = str($result->title)->lower()->toString();
        $slug = str(trim(parse_url($result->url, PHP_URL_PATH) ?: '', '/'))->replace(['-', '/'], ' ')->lower()->toString();

        if ($needle === '' || ($title !== $needle && $slug !== $needle)) {
            return $result;
        }

        return $this->withScore($result, $result->score + (float) config('capell-search.ranking.exact_match_boost', 25.0));
    }

    private function applyRecencyBoost(SearchResultData $result): SearchResultData
    {
        if (! $result->updatedAt instanceof CarbonInterface) {
            return $result;
        }

        $recencyDays = max(1, (int) config('capell-search.ranking.recency_days', 90));
        $ageDays = max(0, now()->diffInDays($result->updatedAt));

        if ($ageDays >= $recencyDays) {
            return $result;
        }

        return $this->withScore($result, $result->score + (($recencyDays - $ageDays) / $recencyDays));
    }

    /**
     * @param  array<string, int>  $clickCounts
     */
    private function applyClickBoost(SearchResultData $result, array $clickCounts): SearchResultData
    {
        $clicks = $clickCounts[$result->url] ?? 0;

        if ($clicks <= 0) {
            return $result;
        }

        $weight = max(0.0, (float) config('capell-search.ranking.click_boost_weight', 0.25));

        return $this->withScore($result, $result->score + min(10.0, $clicks * $weight));
    }

    private function withScore(SearchResultData $result, float $score): SearchResultData
    {
        return new SearchResultData(
            title: $result->title,
            url: $result->url,
            excerpt: $result->excerpt,
            type: $result->type,
            score: $score,
            typeLabel: $result->typeLabel,
            sourceKey: $result->sourceKey,
            updatedAt: $result->updatedAt,
            meta: $result->meta,
            promoted: $result->promoted,
        );
    }

    /**
     * @return array<string, int>
     */
    private function clickCounts(): array
    {
        if (! SchemaFacade::hasTable((new SearchLog)->getTable())) {
            return [];
        }

        return SearchLog::query()
            ->whereNotNull('clicked_result_url')
            ->selectRaw('clicked_result_url, count(*) as clicks')
            ->groupBy('clicked_result_url')
            ->pluck('clicks', 'clicked_result_url')
            ->map(static fn (mixed $clicks): int => (int) $clicks)
            ->all();
    }

    /**
     * @param  Collection<int, SearchResultData>  $items
     * @return Collection<int, SearchResultData>
     */
    private function sortByWeightedScore(Collection $items): Collection
    {
        return $items
            ->values()
            ->sortByDesc(static fn (SearchResultData $result, int $index): array => [
                $result->promoted ? 1 : 0,
                $result->score,
                -$index,
            ]);
    }
}
