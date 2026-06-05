<?php

declare(strict_types=1);

namespace Capell\Search\Drivers;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Support\SearchableSourceRegistry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Search driver backed by Laravel Scout.
 *
 * The registered model classes must use Laravel Scout's Searchable trait.
 */
class ScoutSearch implements Search
{
    private const int MINIMUM_PER_PAGE = 1;

    private const int MAXIMUM_PER_PAGE = 100;

    private readonly SearchableSourceRegistry $registry;

    /**
     * @param  SearchableSourceRegistry|class-string  $registry  Registry or legacy Scout model class.
     */
    public function __construct(
        SearchableSourceRegistry|string $registry,
        private readonly string $urlColumn = 'slug',
        private readonly string $typeColumn = 'type',
        private readonly int $excerptLength = 200,
    ) {
        if ($registry instanceof SearchableSourceRegistry) {
            $this->registry = $registry;

            return;
        }

        $this->registry = new SearchableSourceRegistry;
        $this->registry->register(new SearchableSourceData(
            key: 'default',
            label: 'Default',
            modelClass: $registry,
            type: 'page',
            enabledByDefault: true,
        ));
    }

    public function search(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
    ): LengthAwarePaginator {
        $query = trim($query);
        $perPage = max(self::MINIMUM_PER_PAGE, min(self::MAXIMUM_PER_PAGE, $perPage));
        $page = max(1, $page);

        if ($query === '') {
            return new Paginator([], 0, $perPage, $page);
        }

        $results = $this->registry
            ->enabled()
            ->filter(fn (SearchableSourceData $source): bool => $this->sourceMatchesFilters($source, $filters))
            ->flatMap(fn (SearchableSourceData $source): Collection => $this->searchSource(
                source: $source,
                query: $query,
                perPage: $perPage * $page,
                siteId: $siteId,
                languageId: $languageId,
                filters: $filters,
            ))
            ->filter(static fn (SearchResultData $result): bool => $result->title !== '' && $result->url !== '/')
            ->unique(static fn (SearchResultData $result): string => $result->url)
            ->sortByDesc(static fn (SearchResultData $result): float => $result->score)
            ->values();

        $items = $results
            ->forPage($page, $perPage)
            ->values();

        return new Paginator($items, $results->count(), $perPage, $page);
    }

    public function highlight(string $text, string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '/(' . preg_quote($query, '/') . ')/i';

        return (string) preg_replace($pattern, '<mark>$1</mark>', $escaped);
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    private function searchSource(
        SearchableSourceData $source,
        string $query,
        int $perPage,
        ?int $siteId,
        ?int $languageId,
        ?SearchFilterData $filters,
    ): Collection {
        $builder = ($source->modelClass)::search($query);

        if ($source->indexName !== null && is_callable([$builder, 'within'])) {
            call_user_func([$builder, 'within'], $source->indexName);
        }

        if ($siteId !== null && is_callable([$builder, 'where'])) {
            call_user_func([$builder, 'where'], 'site_id', $siteId);
        }

        if ($languageId !== null && is_callable([$builder, 'where'])) {
            call_user_func([$builder, 'where'], 'language_id', $languageId);
        }

        if ($filters instanceof SearchFilterData && $filters->types !== [] && is_callable([$builder, 'whereIn'])) {
            call_user_func([$builder, 'whereIn'], $this->typeColumn, $filters->types);
        }

        /** @var LengthAwarePaginator<int, object> $paginator */
        $paginator = $builder->paginate(perPage: $perPage, page: 1);

        return (new Collection($paginator->items()))
            ->filter(fn (object $model): bool => $this->isPublicSearchPayload($this->publicSearchPayload($model)))
            ->map(fn (object $model): SearchResultData => $this->mapModelToResult($model, $source, $query));
    }

    private function mapModelToResult(object $model, SearchableSourceData $source, string $query): SearchResultData
    {
        $row = $this->publicSearchPayload($model);
        $title = (string) ($row['title'] ?? '');
        $excerptRaw = (string) ($row['excerpt'] ?? $row['body'] ?? '');
        $url = $this->resolveUrl($model, $row, $source);

        return new SearchResultData(
            title: $title,
            url: $this->normalizeUrl($url),
            excerpt: $this->truncate($excerptRaw),
            type: (string) ($row['type'] ?? $row[$this->typeColumn] ?? $source->type),
            score: $this->score($title . ' ' . $excerptRaw, $query) * $source->weight,
            typeLabel: null,
            sourceKey: $source->key,
            updatedAt: $this->updatedAt($row),
            meta: is_array($row['meta'] ?? null) ? $row['meta'] : [],
        );
    }

    private function sourceMatchesFilters(SearchableSourceData $source, ?SearchFilterData $filters): bool
    {
        if (! $filters instanceof SearchFilterData || $filters->isEmpty()) {
            return true;
        }

        if ($filters->sourceKeys !== [] && ! in_array($source->key, $filters->sourceKeys, true)) {
            return false;
        }

        return $filters->types === [] || in_array($source->type, $filters->types, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSearchPayload(object $model): array
    {
        if (method_exists($model, 'toSearchableArray')) {
            $payload = $model->toSearchableArray();

            if (is_array($payload)) {
                return $payload;
            }
        }

        if ($model instanceof Arrayable) {
            $payload = $model->toArray();

            return is_array($payload) ? $payload : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPublicSearchPayload(array $row): bool
    {
        $status = $row['status'] ?? null;

        if ($status !== null && $status !== 'published') {
            return false;
        }

        foreach (['is_public', 'published', 'is_published'] as $flag) {
            if (array_key_exists($flag, $row) && $row[$flag] !== true) {
                return false;
            }
        }

        foreach (['private', 'is_private'] as $flag) {
            if (array_key_exists($flag, $row) && $row[$flag] === true) {
                return false;
            }
        }

        if (array_key_exists('visibility', $row) && $row['visibility'] !== 'public') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveUrl(object $model, array $row, SearchableSourceData $source): string
    {
        if (is_string($row['url'] ?? null) && $row['url'] !== '') {
            return $row['url'];
        }

        if ($source->urlResolver instanceof Closure) {
            $resolvedUrl = ($source->urlResolver)($model, $row);

            if (is_string($resolvedUrl)) {
                return $resolvedUrl;
            }
        }

        return (string) ($row[$this->urlColumn] ?? '');
    }

    private function normalizeUrl(string $url): string
    {
        if ($url === '') {
            return '/';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return '/' . ltrim($url, '/');
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= $this->excerptLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $this->excerptLength)) . '...';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function updatedAt(array $row): ?CarbonInterface
    {
        $value = $row['updated_at'] ?? $row['updatedAt'] ?? null;

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function score(string $haystack, string $needle): float
    {
        return (float) substr_count(mb_strtolower($haystack), mb_strtolower($needle));
    }
}
