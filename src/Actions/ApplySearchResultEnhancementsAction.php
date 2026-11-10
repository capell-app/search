<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Core\Actions\ResolvePublicPageableMorphTypesAction;
use Capell\Core\Actions\SiteDomains\ResolveSiteDomainAction;
use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Data\SiteDomains\SiteDomainResolutionData;
use Capell\Core\Data\SiteDomains\SiteRequestTargetData;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Capell\Search\Data\PromotedSearchResultData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Models\SearchLog;
use Capell\Search\Support\PubliclyReadableSearchPaginator;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * @method static Paginator<int, SearchResultData> run(LengthAwarePaginator<int, SearchResultData> $results, string $normalizedQuery, ?int $siteId = null, ?int $languageId = null)
 */
final class ApplySearchResultEnhancementsAction
{
    use AsFake;
    use AsObject;

    private const string CLICK_COUNTS_CACHE_PREFIX = 'capell-search:click-counts';

    public static function forgetClickCountsCache(?int $siteId = null, ?int $languageId = null): void
    {
        Cache::forget(self::clickCountsCacheKey(null, null));

        if ($siteId !== null) {
            Cache::forget(self::clickCountsCacheKey($siteId, null));
        }

        if ($languageId !== null) {
            Cache::forget(self::clickCountsCacheKey(null, $languageId));
        }

        if ($siteId !== null && $languageId !== null) {
            Cache::forget(self::clickCountsCacheKey($siteId, $languageId));
        }
    }

    /**
     * @param  LengthAwarePaginator<int, SearchResultData>  $results
     * @return Paginator<int, SearchResultData>
     */
    public function handle(
        LengthAwarePaginator $results,
        string $normalizedQuery,
        ?int $siteId = null,
        ?int $languageId = null,
    ): Paginator {
        $promotedResults = ResolvePromotedSearchResultsAction::run($normalizedQuery);
        $sourceWeights = $this->sourceWeights();
        $typeLabels = $this->typeLabels();
        $clickCounts = $this->clickCounts($siteId, $languageId);

        // Promotions that may be shown at all: currently published and
        // publicly readable. They appear only on the first page, never on a
        // page reached by continuing a read, and are left out of the organic
        // results on every page so none repeats across pages.
        // The address is sanitised first, so eligibility judges the one that is shown.
        $eligiblePromotions = $this->publiclyReadable($this->published(new Collection($promotedResults)
            ->map(static fn (PromotedSearchResultData $promotion): ?SearchResultData => SanitizeSearchResultAction::run($promotion->toSearchResult()))
            ->filter(static fn (?SearchResultData $result): bool => $result instanceof SearchResultData)
            ->values()), $siteId, $languageId);
        $isFirstPage = $results->currentPage() === 1
            && (! $results instanceof PubliclyReadableSearchPaginator || ! $results->isContinuation());
        $promotedItems = $isFirstPage ? $eligiblePromotions : new Collection;

        $promotedUrls = $eligiblePromotions
            ->map(static fn (SearchResultData $promotion): string => self::comparableUrl($promotion->url))
            ->all();

        $items = $promotedItems
            ->merge(new Collection($results->items())->reject(
                static fn (SearchResultData $result): bool => in_array(self::comparableUrl($result->url), $promotedUrls, true),
            ))
            ->map(fn (SearchResultData $result): ?SearchResultData => SanitizeSearchResultAction::run($result))
            ->filter(static fn (?SearchResultData $result): bool => $result instanceof SearchResultData)
            ->map(fn (SearchResultData $result): SearchResultData => $this->enhanceResult(
                result: $result,
                normalizedQuery: $normalizedQuery,
                typeLabels: $typeLabels,
                sourceWeights: $sourceWeights,
                clickCounts: $clickCounts,
            ));

        $items = $this->sortByWeightedScore($items);

        if ($results instanceof PubliclyReadableSearchPaginator) {
            return $results->withItems($items->values());
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

    private static function clickCountsCacheKey(?int $siteId, ?int $languageId): string
    {
        $siteScope = $siteId === null ? 'all-sites' : 'site-' . $siteId;
        $languageScope = $languageId === null ? 'all-languages' : 'language-' . $languageId;

        return self::CLICK_COUNTS_CACHE_PREFIX . ':' . (new SearchLog)->getTable() . ':' . $siteScope . ':' . $languageScope;
    }

    /**
     * One form for a promotion target and an organic result, so a relative
     * promotion still replaces its absolute organic duplicate.
     */
    private static function comparableUrl(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = rtrim(request()->getSchemeAndHttpHost(), '/') . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return mb_strtolower(is_string($host) ? $host : '') . '/' . trim(is_string($path) ? $path : '', '/');
    }

    /**
     * A promotion is shown only when its target can be shown to be a
     * published page: the address (relative ones resolved against this
     * request) is matched to its site and publishing domain, whatever the
     * search's own site scope or host, and that site must hold an enabled
     * address there whose page is published. A target whose site cannot be
     * resolved, or that is no published page, is dropped.
     *
     * @param  Collection<int, SearchResultData>  $results
     * @return Collection<int, SearchResultData>
     */
    private function published(Collection $results): Collection
    {
        $morphTypes = ResolvePublicPageableMorphTypesAction::run();

        if ($morphTypes === []) {
            return new Collection;
        }

        $origin = rtrim(request()->getSchemeAndHttpHost(), '/');
        /** @var array<string, Collection<int, Site>> $sitesByHost */
        $sitesByHost = [];

        return $results->filter(static function (SearchResultData $result) use ($origin, $morphTypes, &$sitesByHost): bool {
            $url = str_starts_with($result->url, '/') && ! str_starts_with($result->url, '//')
                ? $origin . $result->url
                : $result->url;
            $host = parse_url($url, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                return false;
            }

            try {
                $sitesByHost[$host] ??= Site::query()->excludingPreview()->whereHas('siteDomains', static fn (Builder $query): Builder => $query->where(static fn (Builder $domains): Builder => $domains->whereRaw('LOWER(domain) = ?', [mb_strtolower($host)])->orWhereNull('domain')))->with('siteDomains')->get();
                $resolution = ResolveSiteDomainAction::run(SiteRequestTargetData::fromUrl($url), $sitesByHost[$host]);
            } catch (Throwable) {
                return false;
            }

            if (! $resolution instanceof SiteDomainResolutionData) {
                return false;
            }

            $domain = $resolution->siteDomainForRequest();
            $path = '/' . trim($resolution->relativePath, '/');

            return PageUrl::query()
                ->where('site_id', $domain->site_id)
                ->when($domain->language_id !== null, static fn (Builder $query): Builder => $query->where('language_id', $domain->language_id))
                ->where('url', $path)
                ->enabled()
                ->whereHasMorph('pageable', $morphTypes, static fn (BuilderContract $pageable): BuilderContract => $pageable->publishedDate())
                ->exists();
        })->values();
    }

    /**
     * Promotions name their own URLs, so one is shown only while an anonymous
     * visitor may read the page it points to.
     *
     * @param  Collection<int, SearchResultData>  $results
     * @return Collection<int, SearchResultData>
     */
    private function publiclyReadable(Collection $results, ?int $siteId, ?int $languageId): Collection
    {
        $results = $results->values();
        $decisions = resolve(PublicPageReadEligibilityRegistry::class)->allowsMany(array_values($results->map(static fn (SearchResultData $result): PublicPageReadCandidateData => new PublicPageReadCandidateData(
            url: $result->url,
            siteId: $siteId,
            languageId: $languageId,
            publishedOn: request()->getSchemeAndHttpHost(),
        ))->all()));

        return $results->filter(static fn (SearchResultData $result, int $position): bool => $decisions[$position])->values();
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
     * @param  array<string, string>  $typeLabels
     * @param  array<string, float>  $sourceWeights
     * @param  array<string, int>  $clickCounts
     */
    private function enhanceResult(
        SearchResultData $result,
        string $normalizedQuery,
        array $typeLabels,
        array $sourceWeights,
        array $clickCounts,
    ): SearchResultData {
        $result = $this->applyTypeLabel($result, $typeLabels);
        $result = $this->applySourceWeight($result, $sourceWeights);
        $result = $this->applyExactMatchBoost($result, $normalizedQuery);
        $result = $this->applyRecencyBoost($result);

        return $this->applyClickBoost($result, $clickCounts);
    }

    /**
     * @param  array<string, string>  $typeLabels
     */
    private function applyTypeLabel(SearchResultData $result, array $typeLabels): SearchResultData
    {
        $label = ResolveSearchResultTypeLabelAction::run($result->type, $typeLabels);

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
        $resultPath = parse_url($result->url, PHP_URL_PATH);
        $clickHash = is_string($resultPath) && $resultPath !== ''
            ? HashSearchRetentionValueAction::run($resultPath)
            : null;
        $clicks = $clickHash === null ? 0 : ($clickCounts[$clickHash] ?? 0);

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
    private function clickCounts(?int $siteId, ?int $languageId): array
    {
        if (! SchemaFacade::hasTable((new SearchLog)->getTable())) {
            return [];
        }

        $cacheSeconds = max(0, (int) config('capell-search.ranking.click_counts_cache_seconds', 60));

        if ($cacheSeconds <= 0) {
            return $this->queryClickCounts($siteId, $languageId);
        }

        return Cache::remember(
            self::clickCountsCacheKey($siteId, $languageId),
            $cacheSeconds,
            fn (): array => $this->queryClickCounts($siteId, $languageId),
        );
    }

    /**
     * @return array<string, int>
     */
    private function queryClickCounts(?int $siteId, ?int $languageId): array
    {
        $query = SearchLog::query()
            ->whereNotNull('clicked_result_hash')
            ->selectRaw('clicked_result_hash, count(*) as clicks')
            ->groupBy('clicked_result_hash');

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        if ($languageId !== null) {
            $query->where('language_id', $languageId);
        }

        return $query
            ->pluck('clicks', 'clicked_result_hash')
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
