<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Capell\Search\Data\SearchableSourceData;
use Illuminate\Support\Collection;

final class SearchableSourceRegistry
{
    /** @var array<string, SearchableSourceData> */
    private array $sources = [];

    public function register(SearchableSourceData $source): void
    {
        $this->sources[$source->key] = $source;
    }

    public function get(string $key): ?SearchableSourceData
    {
        return $this->sources[$key] ?? null;
    }

    /**
     * @return Collection<string, SearchableSourceData>
     */
    public function all(): Collection
    {
        return collect($this->sources);
    }

    /**
     * @return Collection<string, SearchableSourceData>
     */
    public function enabled(): Collection
    {
        return $this->all()
            ->filter(static fn (SearchableSourceData $source): bool => $source->enabled());
    }
}
