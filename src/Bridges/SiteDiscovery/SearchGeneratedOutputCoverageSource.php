<?php

declare(strict_types=1);

namespace Capell\Search\Bridges\SiteDiscovery;

use Capell\Core\Facades\CapellCore;
use Capell\DiscoveryFoundation\Data\PublicUrlRegistryEntryData;
use Capell\DiscoveryFoundation\Enums\PublicUrlIndexability;
use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;

final class SearchGeneratedOutputCoverageSource implements GeneratedOutputCoverageSource
{
    public static function register(Application $app): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/site-discovery')) {
            return;
        }

        $app->singleton(self::class);
        $app->tag([self::class], GeneratedOutputCoverageSource::TAG);
    }

    public function key(): string
    {
        return GeneratedOutputCoverageSource::SEARCH;
    }

    /**
     * @param  Collection<int, PublicUrlRegistryEntryData>  $registryEntries
     * @return Collection<int, string>
     */
    public function coveredUrls(Collection $registryEntries): Collection
    {
        return $registryEntries
            ->filter(fn (PublicUrlRegistryEntryData $entry): bool => $entry->indexability === PublicUrlIndexability::Indexable)
            ->pluck('canonicalUrl')
            ->unique()
            ->values();
    }
}
