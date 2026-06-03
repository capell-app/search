<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class SearchFilterData extends Data
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $sourceKeys
     */
    public function __construct(
        public readonly array $types = [],
        public readonly array $sourceKeys = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->types === [] && $this->sourceKeys === [];
    }
}
