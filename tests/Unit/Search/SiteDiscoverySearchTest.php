<?php

declare(strict_types=1);

use Capell\Search\Data\SearchResultData;
use Capell\Search\Drivers\SiteDiscoverySearch;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Illuminate\Support\Collection;

test('site discovery search returns indexable public registry URLs', function (): void {
    $search = new SiteDiscoverySearch(new Collection([
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/services/cms-hosting',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 1,
            siteId: 1,
            languageId: 1,
            languageCode: 'en',
            routeName: 'capell.pages.show',
            contentType: PublicUrlContentType::Page,
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/services/private-hosting',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 1,
            siteId: 1,
            languageId: 1,
            indexability: PublicUrlIndexability::NoIndex,
            contentType: PublicUrlContentType::Page,
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/fr/services/cms-hosting',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 2,
            siteId: 1,
            languageId: 2,
            languageCode: 'fr',
            contentType: PublicUrlContentType::Page,
        ),
    ]));

    $results = $search->search('cms hosting', siteId: 1, languageId: 1);
    $firstResult = $results->items()[0] ?? null;

    expect($results->total())->toBe(1)
        ->and($firstResult)->toBeInstanceOf(SearchResultData::class)
        ->and($firstResult?->title)->toBe('Cms Hosting')
        ->and($firstResult?->url)->toBe('https://example.com/services/cms-hosting')
        ->and($firstResult?->type)->toBe('page')
        ->and($firstResult?->excerpt)->toContain('capell-app/core');
});

test('site discovery search matches route package and content type metadata', function (): void {
    $search = new SiteDiscoverySearch(new Collection([
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/docs/install',
            sourcePackage: 'capell-app/knowledge-base',
            siteKey: 'main',
            languageKey: 'en',
            routeName: 'knowledge-base.article',
            contentType: PublicUrlContentType::Article,
        ),
    ]));

    $results = $search->search('knowledge base article');
    $firstResult = $results->items()[0] ?? null;

    expect($results->total())->toBe(1)
        ->and($firstResult?->url)->toBe('https://example.com/docs/install')
        ->and($firstResult?->type)->toBe('article');
});

test('site discovery search paginates and highlights safely', function (): void {
    $search = new SiteDiscoverySearch(new Collection([
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/services/cms-hosting',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 1,
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.com/blog/cms-launch',
            sourcePackage: 'capell-app/blog',
            siteKey: 1,
            languageKey: 1,
            contentType: PublicUrlContentType::Article,
        ),
    ]));

    $results = $search->search('cms', perPage: 1, page: 2);
    $highlighted = $search->highlight('<CMS guide>', 'CMS');

    expect($results->total())->toBe(2)
        ->and($results->items())->toHaveCount(1)
        ->and($highlighted)->toContain('<mark>CMS</mark>')
        ->and($highlighted)->toContain('&lt;')
        ->and($highlighted)->not->toContain('<CMS');
});
