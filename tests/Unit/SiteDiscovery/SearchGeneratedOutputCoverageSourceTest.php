<?php

declare(strict_types=1);

use Capell\Search\Support\SiteDiscovery\SearchGeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;

it('reports unique indexable registry urls as search coverage', function (): void {
    $source = new SearchGeneratedOutputCoverageSource;

    $urls = $source->coveredUrls(collect([
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.test/about',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 'en',
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.test/about',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 'en',
        ),
        new PublicUrlRegistryEntryData(
            canonicalUrl: 'https://example.test/private',
            sourcePackage: 'capell-app/core',
            siteKey: 1,
            languageKey: 'en',
            indexability: PublicUrlIndexability::NoIndex,
        ),
    ]));

    expect($source->key())->toBe(GeneratedOutputCoverageSource::SEARCH)
        ->and($urls->all())->toBe(['https://example.test/about']);
});
