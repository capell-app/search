<?php

declare(strict_types=1);

namespace Capell\Search\Support\SiteDiscovery;

use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Data\PublicUrlRegistryEntryData;
use Capell\SiteDiscovery\Enums\PublicUrlIndexability;
use Illuminate\Support\Collection;

final class SearchGeneratedOutputCoverageSource implements GeneratedOutputCoverageSource
{
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
