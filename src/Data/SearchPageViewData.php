<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class SearchPageViewData
{
    /**
     * @param  LengthAwarePaginator<int, SearchResultData>  $results
     * @param  Collection<int, array{title: string, excerpt: string}>  $highlightedResults
     * @param  list<SearchFacetGroupData>  $facetGroups
     */
    public function __construct(
        public string $query,
        public LengthAwarePaginator $results,
        public Collection $highlightedResults,
        public array $facetGroups,
        public ?string $clickTrackingToken,
    ) {}

    /**
     * @return array{
     *     highlightedResults: Collection<int, array{title: string, excerpt: string}>,
     *     facetGroups: list<SearchFacetGroupData>,
     *     clickTrackingToken: string|null,
     *     query: string,
     *     results: LengthAwarePaginator<int, SearchResultData>
     * }
     */
    public function toViewData(): array
    {
        return [
            'highlightedResults' => $this->highlightedResults,
            'facetGroups' => $this->facetGroups,
            'clickTrackingToken' => $this->clickTrackingToken,
            'query' => $this->query,
            'results' => $this->results,
        ];
    }
}
