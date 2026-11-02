<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;

/** @template TValue
 * @extends LengthAwarePaginator<int, TValue>
 */
final class ExpandedSearchPaginator extends LengthAwarePaginator
{
    /** @param Arrayable<int, TValue>|array<int, TValue> $items */
    public function __construct(Arrayable|array $items, int $total, int $perPage, int $currentPage, public readonly bool $truncated)
    {
        parent::__construct($items, $total, $perPage, $currentPage);
    }
}
