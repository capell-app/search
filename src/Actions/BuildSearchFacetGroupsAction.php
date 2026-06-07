<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Data\SearchFacetGroupData;
use Capell\Search\Data\SearchFacetOptionData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class BuildSearchFacetGroupsAction
{
    use AsAction;

    public function __construct(
        private Search $search,
        private SearchableSourceRegistry $registry,
    ) {}

    /**
     * @return list<SearchFacetGroupData>
     */
    public function handle(
        Request $request,
        string $query,
        SearchFilterData $filters,
        ?int $siteId = null,
        ?int $languageId = null,
    ): array {
        $normalizedQuery = NormalizeSearchQueryAction::run($query);
        $minimumLength = (int) ResolveSearchSettingAction::run('minimum_query_length', 'capell-search.minimum_query_length', 2);

        if (! (bool) config('capell-search.filters.enabled', true) || $normalizedQuery === '' || mb_strlen($normalizedQuery) < $minimumLength) {
            return [];
        }

        $sources = $this->registry->enabled();

        return array_values(array_filter([
            $this->typeGroup($request, $normalizedQuery, $filters, $sources, $siteId, $languageId),
            $this->sourceGroup($request, $normalizedQuery, $filters, $sources, $siteId, $languageId),
        ], static fn (?SearchFacetGroupData $group): bool => $group instanceof SearchFacetGroupData && $group->hasOptions()));
    }

    /**
     * @param  Collection<string, SearchableSourceData>  $sources
     */
    private function typeGroup(
        Request $request,
        string $query,
        SearchFilterData $filters,
        Collection $sources,
        ?int $siteId,
        ?int $languageId,
    ): SearchFacetGroupData {
        $types = $sources
            ->mapToGroups(static fn (SearchableSourceData $source): array => [$source->type => $source])
            ->map(static fn (Collection $group, string $type): string => ResolveSearchResultTypeLabelAction::run($type))
            ->sort()
            ->all();

        $options = [];

        foreach ($types as $type => $label) {
            $selected = in_array($type, $filters->types, true);
            $count = $this->count($query, new SearchFilterData(
                types: [$type],
                sourceKeys: $filters->sourceKeys,
            ), $siteId, $languageId);

            if ($count === 0 && ! $selected) {
                continue;
            }

            $options[] = new SearchFacetOptionData(
                key: $type,
                label: $label,
                count: $count,
                selected: $selected,
                url: $this->url($request, 'type', $type, $selected),
            );
        }

        return new SearchFacetGroupData(
            key: 'type',
            label: __('capell-search::generic.filter_by_type'),
            options: $options,
        );
    }

    /**
     * @param  Collection<string, SearchableSourceData>  $sources
     */
    private function sourceGroup(
        Request $request,
        string $query,
        SearchFilterData $filters,
        Collection $sources,
        ?int $siteId,
        ?int $languageId,
    ): SearchFacetGroupData {
        $options = [];

        foreach ($sources->sortBy('label') as $source) {
            $selected = in_array($source->key, $filters->sourceKeys, true);
            $count = $this->count($query, new SearchFilterData(
                types: $filters->types,
                sourceKeys: [$source->key],
            ), $siteId, $languageId);

            if ($count === 0 && ! $selected) {
                continue;
            }

            $options[] = new SearchFacetOptionData(
                key: $source->key,
                label: $source->label,
                count: $count,
                selected: $selected,
                url: $this->url($request, 'source', $source->key, $selected),
            );
        }

        return new SearchFacetGroupData(
            key: 'source',
            label: __('capell-search::generic.filter_by_source'),
            options: $options,
        );
    }

    private function count(string $query, SearchFilterData $filters, ?int $siteId, ?int $languageId): int
    {
        return $this->search->search(
            query: $query,
            perPage: 1,
            page: 1,
            siteId: $siteId,
            languageId: $languageId,
            filters: $filters,
        )->total();
    }

    private function url(Request $request, string $key, string $value, bool $selected): string
    {
        $parameters = $request->query();
        unset($parameters['page']);

        $values = data_get($parameters, $key, []);
        $values = is_array($values) ? $values : [$values];
        $values = array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $values),
            static fn (string $item): bool => $item !== '',
        ));

        $parameters[$key] = $selected
            ? array_values(array_diff($values, [$value]))
            : array_values(array_unique([...$values, $value]));

        if ($parameters[$key] === []) {
            unset($parameters[$key]);
        }

        return route('capell-frontend.search', $parameters);
    }
}
