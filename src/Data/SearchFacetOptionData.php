<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class SearchFacetOptionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $count,
        public readonly bool $selected,
        public readonly string $url,
    ) {}
}
