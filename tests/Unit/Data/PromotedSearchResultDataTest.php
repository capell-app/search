<?php

declare(strict_types=1);

use Capell\Search\Data\PromotedSearchResultData;

test('preserves absolute promoted urls and normalises relative paths', function (string $configuredUrl, string $expectedUrl): void {
    $result = new PromotedSearchResultData(
        queries: ['capell'],
        title: 'Capell',
        url: $configuredUrl,
    );

    expect($result->toSearchResult()->url)->toBe($expectedUrl);
})->with([
    'absolute url' => ['https://example.test/capell', 'https://example.test/capell'],
    'relative path' => ['/capell', '/capell'],
]);
