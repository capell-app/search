<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Override;

final class RecordingSearch implements Search
{
    /** @var list<array{query: string, perPage: int, page: int, siteId: ?int, languageId: ?int}> */
    public array $calls = [];

    public function __construct(private readonly Search $driver) {}

    /** @return LengthAwarePaginator<int, SearchResultData> */
    #[Override]
    public function search(string $query, int $perPage = 10, int $page = 1, ?int $siteId = null, ?int $languageId = null, ?SearchFilterData $filters = null): LengthAwarePaginator
    {
        $this->calls[] = compact('query', 'perPage', 'page', 'siteId', 'languageId');

        return $this->driver->search($query, $perPage, $page, $siteId, $languageId, $filters);
    }

    #[Override]
    public function highlight(string $text, string $query): string
    {
        return $this->driver->highlight($text, $query);
    }
}
