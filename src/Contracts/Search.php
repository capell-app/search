<?php

declare(strict_types=1);

namespace Capell\Search\Contracts;

use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface for site search backends. A site can use the default Site Discovery
 * driver, opt into database or Scout, or bind a custom implementation.
 */
interface Search
{
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
    ): LengthAwarePaginator;

    /**
     * Return public-safe HTML for highlighted search text.
     *
     * Implementations must escape the full input text and only add trusted
     * highlight markup, currently `<mark>...</mark>`.
     */
    public function highlight(string $text, string $query): string;
}
