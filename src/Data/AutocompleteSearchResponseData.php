<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class AutocompleteSearchResponseData extends Data
{
    /**
     * @param  list<AutocompleteSearchResultData>  $results
     */
    public function __construct(
        public readonly string $query,
        public readonly int $minimumLength,
        public readonly array $results,
        public readonly string $allResultsUrl,
        public readonly SearchQueryMetadataData $metadata,
    ) {}
}
