<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static list<string> run(?string $sourceKey = null, ?int $chunk = null)
 */
final readonly class IndexScoutSearchSourcesAction
{
    use AsAction;

    public function __construct(private SearchableSourceRegistry $registry) {}

    /**
     * @return list<string>
     */
    public function handle(?string $sourceKey = null, ?int $chunk = null): array
    {
        return array_values($this->sources($sourceKey)
            ->filter(static fn (SearchableSourceData $source): bool => method_exists($source->modelClass, 'makeAllSearchable')
                && is_callable([$source->modelClass, 'makeAllSearchable']))
            ->map(function (SearchableSourceData $source) use ($chunk): string {
                $callback = [$source->modelClass, 'makeAllSearchable'];

                if (method_exists($source->modelClass, 'makeAllSearchable') && is_callable($callback)) {
                    $callback($chunk);
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
