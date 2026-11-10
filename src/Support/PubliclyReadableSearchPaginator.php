<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Override;

/**
 * Search results filtered for anonymous public reads, page by page.
 *
 * The full count of readable matches is never computed, so total() is only
 * a lower bound that is enough for next and previous links. Show neither the
 * total nor numbered page links for these results.
 *
 * @template TValue
 *
 * @extends LengthAwarePaginator<int, TValue>
 */
final class PubliclyReadableSearchPaginator extends LengthAwarePaginator
{
    public const string CURSOR_NAME = 'cursor';

    /**
     * @param  Arrayable<int, TValue>|array<int, TValue>  $items
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        Arrayable|array $items,
        int $perPage,
        int $currentPage,
        private readonly bool $hasMore,
        array $options = [],
        private readonly ?string $nextToken = null,
        private readonly bool $fromCursor = false,
    ) {
        $count = count($items instanceof Arrayable ? $items->toArray() : $items);

        // A bounded scan can stop with a short or empty page while more
        // readable results follow, so "more" alone decides the next page.
        $total = $hasMore ? ($currentPage * $perPage) + 1 : (($currentPage - 1) * $perPage) + $count;

        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    public static function hasExactTotal(mixed $results): bool
    {
        return ! $results instanceof self;
    }

    /**
     * The same page with its results replaced (re-ranked or enhanced),
     * keeping the continuation cursor and links.
     *
     * @template TNew
     *
     * @param  Arrayable<int, TNew>|array<int, TNew>  $items
     * @return self<TNew>
     */
    public function withItems(Arrayable|array $items): self
    {
        return new self(
            items: $items,
            perPage: $this->perPage(),
            currentPage: $this->currentPage(),
            hasMore: $this->hasMore,
            options: ['path' => $this->path(), 'pageName' => $this->getPageName(), 'query' => $this->query, 'fragment' => $this->fragment],
            nextToken: $this->nextToken,
            fromCursor: $this->fromCursor,
        );
    }

    /**
     * Continues the read from where this page stopped, so a restricted run
     * of results never makes a request unbounded.
     */
    #[Override]
    public function nextPageUrl(): ?string
    {
        $query = $this->query;
        // The cursor that led here belongs to this page, never to the next.
        unset($query[self::CURSOR_NAME]);

        if ($this->nextToken === null) {
            $this->query = $query;

            return parent::nextPageUrl();
        }

        $query[self::CURSOR_NAME] = $this->nextToken;
        unset($query[$this->pageName]);
        $path = (string) $this->path();

        return $path . (str_contains($path, '?') ? '&' : '?') . Arr::query($query) . $this->buildFragment();
    }

    /**
     * Whether this page continues an earlier one through a cursor. Its page
     * number then says nothing about its place in the results: it is never
     * the first page.
     */
    public function isContinuation(): bool
    {
        return $this->fromCursor;
    }

    #[Override]
    public function previousPageUrl(): ?string
    {
        return $this->fromCursor ? null : parent::previousPageUrl();
    }
}
