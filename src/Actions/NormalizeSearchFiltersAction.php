<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final readonly class NormalizeSearchFiltersAction
{
    use AsFake;
    use AsObject;

    public function __construct(private SearchableSourceRegistry $registry) {}

    public function handle(Request $request): SearchFilterData
    {
        if (! (bool) config('capell-search.filters.enabled', true)) {
            return new SearchFilterData;
        }

        $sources = $this->registry->enabled();
        $allowedTypes = $sources
            ->map(static fn (SearchableSourceData $source): string => $source->type)
            ->unique()
            ->values()
            ->all();
        $allowedSourceKeys = array_values($sources->keys()->values()->all());

        return new SearchFilterData(
            types: $this->normalizeValues($request->query('type'), array_values($allowedTypes)),
            sourceKeys: $this->normalizeValues($request->query('source'), $allowedSourceKeys),
        );
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function normalizeValues(mixed $value, array $allowed): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(collect($values)
            ->map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '')
            ->filter(static fn (string $item): bool => $item !== '' && in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all());
    }
}
