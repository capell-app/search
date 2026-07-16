<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<string> run(?string $sourceKey = null)
 */
final readonly class FlushScoutSearchSourcesAction
{
    use AsFake;
    use AsObject;

    public function __construct(private SearchableSourceRegistry $registry) {}

    /**
     * @return list<string>
     */
    public function handle(?string $sourceKey = null): array
    {
        return array_values($this->sources($sourceKey)
            ->filter(static fn (SearchableSourceData $source): bool => method_exists($source->modelClass, 'removeAllFromSearch')
                && is_callable([$source->modelClass, 'removeAllFromSearch']))
            ->map(function (SearchableSourceData $source): string {
                $callback = [$source->modelClass, 'removeAllFromSearch'];

                if (method_exists($source->modelClass, 'removeAllFromSearch') && is_callable($callback)) {
                    $callback();
                }

                return $source->key;
            })
            ->values()
            ->all());
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
