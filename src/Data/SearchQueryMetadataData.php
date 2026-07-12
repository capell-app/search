<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class SearchQueryMetadataData extends Data
{
    /**
     * @param  list<string>  $expanded
     */
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly ?string $corrected = null,
        public readonly array $expanded = [],
        public readonly ?SearchFilterData $filters = null,
    ) {}
}
