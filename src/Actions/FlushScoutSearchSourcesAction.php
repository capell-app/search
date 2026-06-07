<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class FlushScoutSearchSourcesAction
{
    use AsAction;

    public function __construct(private SearchableSourceRegistry $registry) {}

    /**
     * @return list<string>
     */
    public function handle(?string $sourceKey = null): array
    {
        return $this->sources($sourceKey)
            ->filter(static fn (SearchableSourceData $source): bool => method_exists($source->modelClass, 'removeAllFromSearch'))
            ->map(function (SearchableSourceData $source): string {
                call_user_func([$source->modelClass, 'removeAllFromSearch']);

                return $source->key;
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, SearchableSourceData>
     */
    private function sources(?string $sourceKey): Collection
    {
        $sources = $this->registry->enabled();

        if ($sourceKey === null || $sourceKey === '') {
            return $sources;
        }

        return $sources->filter(static fn (SearchableSourceData $source): bool => $source->key === $sourceKey);
    }
}
