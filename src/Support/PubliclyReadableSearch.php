<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Capell\Core\Support\PublicRead\PublicReadCursor;
use Capell\Core\Support\PublicRead\PublicReadScanner;
use Capell\Search\Actions\SanitizeSearchResultAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;
use Override;

/**
 * Applies public-read eligibility to whichever search driver is bound.
 *
 * Indexes do not know which pages an anonymous visitor may read, so while a
 * package restricts public reads the driver is scanned in batches, filtered,
 * and paginated here: a restricted page leaves no gap, count or page link
 * behind it. Only as many matches are read as the requested page needs, and
 * never more than PublicReadScanner::MAX_SCANNED per request, nor past
 * PublicReadScanner::MAX_POSITION in the driver; a next link carries a sealed
 * cursor that continues the read. No exact total exists, so the results offer
 * only next and previous links. All a visitor learns about results they may
 * not read is that more may follow. The driver's own "more pages"
 * signal decides when it is exhausted, since drivers may return short pages
 * (dropped duplicates or records that no longer exist).
 */
final readonly class PubliclyReadableSearch implements Search
{
    private const int SCAN_BATCH = 50;

    private const string SEEN_CACHE_PREFIX = 'capell-search:shown:';

    /** Longer than a cursor lives, so a valid cursor always finds its state. */
    private const int SEEN_TTL_SECONDS = 7200;

    public function __construct(
        private Search $search,
        private PublicPageReadEligibilityRegistry $eligibility,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    #[Override]
    public function search(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
    ): LengthAwarePaginator {
        return $this->searchFrom($query, $perPage, $page, $siteId, $languageId, $filters);
    }

    /**
     * Like search(), continuing from a sealed cursor a previous page returned.
     * A cursor that does not open for this exact search (other query,
     * filters, site, language or domain; older public-read rules; expired or
     * altered) starts again at the first page.
     *
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    public function searchFrom(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
        ?string $cursor = null,
    ): LengthAwarePaginator {
        if (! $this->eligibility->hasContributors()) {
            return $this->search->search($query, $perPage, $page, $siteId, $languageId, $filters);
        }

        return $this->read([$query], $perPage, $page, $siteId, $languageId, $filters, $cursor);
    }

    /**
     * Whether results are filtered and bounded here (some package restricts
     * public reads), so callers must page through this decorator's cursor
     * instead of reading totals.
     */
    public function restrictsReads(): bool
    {
        return $this->eligibility->hasContributors();
    }

    /**
     * One bounded, cursor-continued read across several expanded queries, in
     * order: each query is read to its end (or the driver bound) before the
     * next, a result an earlier query in the same read already returned is
     * skipped, and the next link carries one sealed cursor naming the query,
     * driver page and index to continue from. No total exists.
     *
     * @param  non-empty-list<string>  $queries
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    public function searchQueriesFrom(
        array $queries,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
        ?string $cursor = null,
    ): LengthAwarePaginator {
        return $this->read($queries, $perPage, $page, $siteId, $languageId, $filters, $cursor);
    }

    /**
     * The wrapped driver, for a caller that applies public-read eligibility
     * and its own bounded paging itself (the Core agent search boundary), so
     * results are not scanned twice.
     */
    public function unfiltered(): Search
    {
        return $this->search;
    }

    #[Override]
    public function highlight(string $text, string $query): string
    {
        return $this->search->highlight($text, $query);
    }

    /**
     * The addresses already shown by a continued read, exactly. Each batch of
     * newly shown addresses is stored once, immutably and under its own
     * digest, with a reference to the state it extends; a position carries
     * only that reference. A state that can no longer be read restarts the
     * search from its first page rather than risk repeating a result.
     *
     * @param  array<string, int|string|null>|null  $position
     * @return array<string, true>|null null when the state is unreadable
     */
    private function seenFrom(?array $position): ?array
    {
        $key = $position['s'] ?? null;
        $count = $position['n'] ?? null;
        $set = [];

        while (is_string($key) && is_int($count)) {
            $record = Cache::get(self::SEEN_CACHE_PREFIX . $key);

            if (! is_array($record) || ! is_array($record['u'] ?? null)) {
                return null;
            }

            foreach (array_slice($record['u'], 0, $count) as $url) {
                if (is_string($url)) {
                    $set[$url] = true;
                }
            }

            $base = $record['b'] ?? null;
            $key = is_array($base) ? ($base['s'] ?? null) : null;
            $count = is_array($base) ? ($base['n'] ?? null) : null;
        }

        return $set;
    }

    /**
     * @param  array<string, int|string|null>|null  $base
     * @param  list<string>  $urls
     * @return string the key under which the batch is stored
     */
    private function rememberSeen(?array $base, array $urls): string
    {
        $record = ['b' => isset($base['s'], $base['n']) ? ['s' => $base['s'], 'n' => $base['n']] : null, 'u' => $urls];
        $key = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
        Cache::put(self::SEEN_CACHE_PREFIX . $key, $record, self::SEEN_TTL_SECONDS);

        return $key;
    }

    /**
     * @param  non-empty-list<string>  $queries
     * @return LengthAwarePaginator<int, SearchResultData>
     */
    private function read(
        array $queries,
        int $perPage,
        int $page,
        ?int $siteId,
        ?int $languageId,
        ?SearchFilterData $filters,
        ?string $cursor,
    ): LengthAwarePaginator {
        $perPage = max(1, $perPage);
        $batchSize = min(max(self::SCAN_BATCH, $perPage + 1), PublicReadScanner::MAX_SCANNED);
        // The driver is never asked past this page for any query, however a request is built.
        $maxDriverPage = max(1, intdiv(PublicReadScanner::MAX_POSITION, $batchSize));
        $publishedOn = request()->getSchemeAndHttpHost();
        $context = PublicReadCursor::context('search', [$queries, $perPage, $siteId, $languageId, $filters?->toArray(), $publishedOn]);
        $opened = PublicReadCursor::open($cursor, $context);

        // A continuation whose record of shown results is gone restarts instead of repeating them.
        if ($opened instanceof PublicReadCursor && $this->seenFrom($opened->position) === null) {
            $opened = null;
        }

        $page = $opened instanceof PublicReadCursor || $cursor === null ? max(1, $page) : 1;
        $path = null;
        $pageName = 'page';
        /** @var array<string, LengthAwarePaginator<int, mixed>> $driverPages */
        $driverPages = [];
        $lastQuery = count($queries) - 1;

        // A position is a query, a driver page and an index within the results
        // that page returned, so a short page never shifts a cursor.
        $result = resolve(PublicReadScanner::class)->scan(
            fetch: function (?array $after, int $limit) use ($queries, $lastQuery, $batchSize, $maxDriverPage, $siteId, $languageId, $filters, $publishedOn, &$driverPages, &$path, &$pageName): array {
                $queryIndex = max(0, (int) ($after['q'] ?? 0));
                $driverPage = max(1, (int) ($after['p'] ?? 1));
                $start = max(0, (int) ($after['i'] ?? 0));

                if ($queryIndex > $lastQuery || $driverPage > $maxDriverPage) {
                    return ['items' => [], 'positions' => [], 'after' => $after, 'more' => false];
                }

                $results = $driverPages[$queryIndex . ':' . $driverPage] ??= $this->search->search($queries[$queryIndex], $batchSize, $driverPage, $siteId, $languageId, $filters);
                $path ??= $results->path();
                $pageName = $results instanceof Paginator ? $results->getPageName() : $pageName;
                $items = array_values(array_filter($results->items(), static fn (mixed $result): bool => $result instanceof SearchResultData));
                $slice = array_slice($items, $start, $limit);
                $positions = array_map(static fn (int $index): array => ['q' => $queryIndex, 'p' => $driverPage, 'i' => $start + $index + 1], array_keys($slice));
                // Eligibility is decided here, so only readable addresses count
                // as shown. A URL belongs to the first query and position that
                // returns it readable; the state of what was shown is exact.
                $baseRef = isset($after['s'], $after['n']) ? ['s' => $after['s'], 'n' => $after['n']] : null;
                $seen = $this->seenFrom($after) ?? [];
                $candidates = [];
                $batchSeen = [];

                foreach ($slice as $index => $item) {
                    $sanitized = SanitizeSearchResultAction::run($item);

                    if (! $sanitized instanceof SearchResultData || isset($seen[$sanitized->url]) || isset($batchSeen[$sanitized->url])) {
                        continue;
                    }

                    $batchSeen[$sanitized->url] = true;
                    $candidates[$index] = $sanitized;
                }

                $decisions = $candidates === [] ? [] : $this->eligibility->allowsMany(array_map(
                    static fn (SearchResultData $result): PublicPageReadCandidateData => new PublicPageReadCandidateData(
                        url: $result->url,
                        siteId: $siteId,
                        languageId: $languageId,
                        publishedOn: $publishedOn,
                    ),
                    array_values($candidates),
                ));
                $kept = [];
                $keptIndexes = [];

                foreach (array_keys($candidates) as $decision => $index) {
                    if (($decisions[$decision] ?? false) === true) {
                        $kept[] = $candidates[$index];
                        $keptIndexes[] = $index;
                    }
                }

                $recordKey = $kept === [] ? null : $this->rememberSeen($baseRef, array_map(static fn (SearchResultData $result): string => $result->url, $kept));
                $endRef = $recordKey === null ? $baseRef : ['s' => $recordKey, 'n' => count($kept)];
                $keptPositions = [];

                foreach ($keptIndexes as $order => $index) {
                    $keptPositions[] = [...$positions[$index], 's' => $recordKey, 'n' => $order + 1];
                }

                if ($start + count($slice) < count($items)) {
                    return ['items' => $kept, 'positions' => $keptPositions, 'after' => [...($positions[count($positions) - 1] ?? $after ?? []), 's' => $endRef['s'] ?? null, 'n' => $endRef['n'] ?? null], 'more' => true, 'scanned' => max(1, count($slice))];
                }

                $queryContinues = $results->hasMorePages() && $driverPage < $maxDriverPage;

                return [
                    'items' => $kept,
                    'positions' => $keptPositions,
                    'after' => $queryContinues
                        ? ['q' => $queryIndex, 'p' => $driverPage + 1, 'i' => 0, 's' => $endRef['s'] ?? null, 'n' => $endRef['n'] ?? null]
                        : ['q' => $queryIndex + 1, 'p' => 1, 'i' => 0, 's' => $endRef['s'] ?? null, 'n' => $endRef['n'] ?? null],
                    'more' => $queryContinues || $queryIndex < $lastQuery,
                    'scanned' => max(1, $batchSize - $start),
                ];
            },
            // The fetch above already returns only readable results.
            readable: static fn (array $results): array => array_fill(0, count($results), true),
            size: $perPage,
            page: $page,
            cursor: $opened,
        );

        return new PubliclyReadableSearchPaginator(
            items: $result->items,
            perPage: $perPage,
            currentPage: $page,
            hasMore: $result->hasMore,
            options: [
                'path' => $path ?? Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
            nextToken: $result->next?->seal($context),
            fromCursor: $opened instanceof PublicReadCursor,
        );
    }
}
