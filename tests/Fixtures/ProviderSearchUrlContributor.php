<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Illuminate\Support\Collection;
use Override;

final class ProviderSearchUrlContributor implements PublicUrlContributor
{
    public function __construct(private readonly Site $site, private readonly Language $language) {}

    /** @return Collection<int, PublicUrlData> */
    #[Override]
    public function publicUrls(): Collection
    {
        return collect([new PublicUrlData(
            canonicalUrl: 'https://search-provider.test/registry',
            sourcePackage: 'capell-app/search',
            site: $this->site,
            language: $this->language,
            title: 'Provider marker registry',
        )]);
    }
}
