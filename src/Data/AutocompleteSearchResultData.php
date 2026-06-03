<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class AutocompleteSearchResultData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $excerpt,
        public readonly string $type,
        public readonly ?string $typeLabel,
        public readonly float $score,
    ) {}

    public static function fromSearchResult(SearchResultData $result): self
    {
        return new self(
            title: $result->title,
            url: $result->url,
            excerpt: $result->excerpt,
            type: $result->type,
            typeLabel: $result->typeLabel,
            score: $result->score,
        );
    }
}
