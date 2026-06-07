<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class SearchFacetGroupData extends Data
{
    /**
     * @param  list<SearchFacetOptionData>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $options,
    ) {}

    public function hasOptions(): bool
    {
        return $this->options !== [];
    }
}
