<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

final class SearchAdditionalCoverageScoutModel
{
    /** @var list<array<string, mixed>> */
    private static array $records = [
        [
            'title' => 'Capell Guide',
            'body' => 'Capell CMS search coverage',
            'path' => 'docs/capell',
            'kind' => 'guide',
        ],
    ];

    public static function search(string $query): SearchAdditionalCoverageScoutBuilder
    {
        return new SearchAdditionalCoverageScoutBuilder($query, self::$records);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public static function fakeRecords(array $records): void
    {
        self::$records = $records;
    }
}
