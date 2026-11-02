<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Override;

final class BoundedSearch implements Search
{
    /** @return LengthAwarePaginator<int, SearchResultData> */
    #[Override]
    public function search(string $query, int $perPage = 10, int $page = 1, ?int $siteId = null, ?int $languageId = null, ?SearchFilterData $filters = null): LengthAwarePaginator
    {
        $items = [];
        for ($index = 1; $index <= min(100, $perPage); $index++) {
            $items[] = new SearchResultData(title: 'Result ' . $index, url: '/result-' . $index, excerpt: '', score: 1.0);
        }

        return new Paginator($items, 101, $perPage, $page);
    }

    #[Override]
    public function highlight(string $text, string $query): string
    {
        return $text;
    }
}
