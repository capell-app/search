<?php

declare(strict_types=1);

namespace Capell\Search\Drivers;

use Capell\DiscoveryFoundation\Actions\BuildPublicUrlRegistryAction;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\Search\Actions\FlushSiteDiscoverySearchRegistryAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Support\HighlightsEscapedText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final readonly class SiteDiscoverySearch implements Search
{
    /**
     * @param  Collection<int, PublicUrlRegistryEntryData>|null  $registryEntries
     */
    public function __construct(
        private ?Collection $registryEntries = null,
        private int $excerptLength = 200,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    public function search(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
    ): LengthAwarePaginator {
        $query = trim($query);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        if ($query === '' || mb_strlen($query) < 2) {
            return $this->paginate(new Collection, $perPage, $page);
        }

        $normalizedQuery = Str::lower($query);
        $results = $this->registryEntries($siteId, $languageId)
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->canSearchEntry($entry, $siteId, $languageId))
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->matchesFilters($entry, $filters))
            ->map(fn (PublicUrlRegistryEntryData $entry): SearchResultData => $this->entryToResult($entry, $normalizedQuery))
            ->filter(fn (SearchResultData $result): bool => $this->matchesResult($result))
            ->sort(static fn (SearchResultData $left, SearchResultData $right): int => ($right->score <=> $left->score) ?: strcmp($left->url, $right->url))
            ->values();

        return $this->paginate($results, $perPage, $page);
    }

    public function highlight(string $text, string $query): string
    {
        $escapedText = e($text);
        $query = trim($query);

        if ($query === '') {
            return $escapedText;
        }

        return HighlightsEscapedText::apply($escapedText, $query);
    }

    private function matchesFilters(PublicUrlRegistryEntryData $entry, ?SearchFilterData $filters): bool
    {
        return ! $filters instanceof SearchFilterData
            || $filters->types === []
            || in_array($entry->contentType->value, $filters->types, true);
    }

    /**
     * @return Collection<int, PublicUrlRegistryEntryData>
     */
    private function registryEntries(?int $siteId, ?int $languageId): Collection
    {
        if ($this->registryEntries instanceof Collection) {
            return $this->registryEntries;
        }

        if (! class_exists(BuildPublicUrlRegistryAction::class)) {
            return new Collection;
        }

        $ttl = Config::integer('capell-search.site_discovery.registry_cache_seconds', 60);

        if ($ttl <= 0) {
            return $this->buildRegistry($siteId, $languageId);
        }

        // One entry per site and language, under a version that any content change moves on.
        $key = implode(':', [
            'capell-search:site-discovery-registry',
            'v' . FlushSiteDiscoverySearchRegistryAction::version(),
            $siteId ?? 'all',
            $languageId ?? 'all',
        ]);

        /** @var Collection<int, PublicUrlRegistryEntryData> $entries */
        $entries = Cache::remember($key, $ttl, fn (): Collection => $this->buildRegistry($siteId, $languageId));

        return $entries;
    }

    /**
     * @return Collection<int, PublicUrlRegistryEntryData>
     */
    private function buildRegistry(?int $siteId, ?int $languageId): Collection
    {
        /** @var Collection<int, PublicUrlRegistryEntryData> $entries */
        $entries = BuildPublicUrlRegistryAction::run();

        return $entries
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->canSearchEntry($entry, $siteId, $languageId))
            ->values();
    }

    private function canSearchEntry(PublicUrlRegistryEntryData $entry, ?int $siteId, ?int $languageId): bool
    {
        if ($entry->indexability !== PublicUrlIndexability::Indexable) {
            return false;
        }

        if ($siteId !== null && $entry->siteId !== $siteId) {
            return false;
        }

        return $languageId === null || $entry->languageId === $languageId;
    }

    private function entryToResult(PublicUrlRegistryEntryData $entry, string $normalizedQuery): SearchResultData
    {
        $title = $this->titleFromEntry($entry);
        $searchText = $this->searchText($entry, $title);

        return new SearchResultData(
            title: $title,
            url: $entry->canonicalUrl,
            excerpt: Str::limit($this->excerpt($entry, $normalizedQuery), $this->excerptLength),
            type: $entry->contentType->value,
            score: $this->score($searchText, $normalizedQuery),
        );
    }

    private function matchesResult(SearchResultData $result): bool
    {
        return $result->score > 0.0;
    }

    private function titleFromEntry(PublicUrlRegistryEntryData $entry): string
    {
        if (is_string($entry->title) && trim($entry->title) !== '') {
            return trim($entry->title);
        }

        return $this->titleFromUrl($entry->canonicalUrl);
    }

    private function titleFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');

        if ($path === '') {
            return __('capell-search::generic.site_discovery_home_title');
        }

        $lastSegment = basename($path);

        return Str::headline(str_replace(['-', '_'], ' ', $lastSegment));
    }

    private function searchText(PublicUrlRegistryEntryData $entry, string $title): string
    {
        return Str::lower(implode(' ', array_filter([
            $title,
            $entry->bodyText,
            $entry->canonicalUrl,
            $entry->languageCode,
            $entry->contentType->value,
        ], static fn (?string $value): bool => $value !== null && $value !== '')));
    }

    /**
     * A window of the page's own text around the first match, so the result shows why it matched.
     */
    private function excerpt(PublicUrlRegistryEntryData $entry, string $normalizedQuery): string
    {
        $body = trim((string) preg_replace('/\s+/u', ' ', (string) $entry->bodyText));

        if ($body === '') {
            return __('capell-search::generic.site_discovery_result_excerpt', [
                'type' => $entry->contentType->getLabel(),
            ]);
        }

        $terms = array_values(array_filter(
            [$normalizedQuery, ...(preg_split('/\s+/', $normalizedQuery) ?: [])],
            static fn (string $term): bool => $term !== '',
        ));
        $position = null;

        foreach ($terms as $term) {
            $found = mb_stripos($body, $term);

            if ($found !== false) {
                $position = $found;

                break;
            }
        }

        if ($position === null) {
            return Str::limit($body, $this->excerptLength);
        }

        $start = max(0, $position - (int) floor($this->excerptLength / 3));
        $window = mb_substr($body, $start, $this->excerptLength);

        return ($start > 0 ? '… ' : '') . trim($window) . (mb_strlen($body) > $start + $this->excerptLength ? ' …' : '');
    }

    private function score(string $searchText, string $normalizedQuery): float
    {
        if ($searchText === $normalizedQuery) {
            return 100.0;
        }

        if (str_contains($searchText, $normalizedQuery)) {
            return 10.0 + substr_count($searchText, $normalizedQuery);
        }

        $score = 0.0;
        $terms = preg_split('/\s+/', $normalizedQuery) ?: [];

        foreach ($terms as $term) {
            if (is_string($term) && $term !== '' && str_contains($searchText, $term)) {
                $score += 1.0;
            }
        }

        return $score;
    }

    /**
     * @param  Collection<int, SearchResultData>  $results
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    private function paginate(Collection $results, int $perPage, int $page): LengthAwarePaginator
    {
        return new Paginator(
            items: $results->forPage($page, $perPage)->values(),
            total: $results->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
