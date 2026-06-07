<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class AutocompleteQuerySuggestionData extends Data
{
    public function __construct(
        public readonly string $query,
        public readonly int $searches,
        public readonly string $url,
    ) {}
}
