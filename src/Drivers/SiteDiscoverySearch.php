<?php

declare(strict_types=1);

namespace Capell\Search\Drivers;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\SiteDiscovery\Actions\BuildPublicUrlRegistryAction;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
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
        $results = $this->registryEntries()
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->canSearchEntry($entry, $siteId, $languageId))
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $this->matchesFilters($entry, $filters))
            ->map(fn (PublicUrlRegistryEntryData $entry): SearchResultData => $this->entryToResult($entry, $normalizedQuery))
            ->filter(fn (SearchResultData $result): bool => $this->matchesResult($result))
            ->sortByDesc(static fn (SearchResultData $result): float => $result->score)
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

        return preg_replace('/(' . preg_quote(e($query), '/') . ')/i', '<mark>$1</mark>', $escapedText) ?? $escapedText;
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
    private function registryEntries(): Collection
    {
        if ($this->registryEntries instanceof Collection) {
            return $this->registryEntries;
        }

        if (! class_exists(BuildPublicUrlRegistryAction::class)) {
            return new Collection;
        }

        /** @var Collection<int, PublicUrlRegistryEntryData> $entries */
        $entries = BuildPublicUrlRegistryAction::run();

        return $entries;
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
        $title = $this->titleFromUrl($entry->canonicalUrl);
        $searchText = $this->searchText($entry, $title);

        return new SearchResultData(
            title: $title,
            url: $entry->canonicalUrl,
            excerpt: Str::limit($this->excerpt($entry), $this->excerptLength),
            type: $entry->contentType->value,
            score: $this->score($searchText, $normalizedQuery),
        );
    }

    private function matchesResult(SearchResultData $result): bool
    {
        return $result->score > 0.0;
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
            $entry->canonicalUrl,
            $entry->routeName,
            $entry->sourcePackage,
            $entry->languageCode,
            $entry->contentType->value,
        ], static fn (?string $value): bool => $value !== null && $value !== '')));
    }

    private function excerpt(PublicUrlRegistryEntryData $entry): string
    {
        return __('capell-search::generic.site_discovery_result_excerpt', [
            'type' => $entry->contentType->getLabel(),
            'package' => $entry->sourcePackage,
        ]);
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
