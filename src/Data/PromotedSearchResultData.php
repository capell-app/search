<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class PromotedSearchResultData extends Data
{
    /**
     * @param  list<string>  $queries
     */
    public function __construct(
        public readonly array $queries,
        public readonly string $title,
        public readonly string $url,
        public readonly string $excerpt = '',
        public readonly string $type = 'page',
        public readonly float $score = 1000.0,
    ) {}

    public function toSearchResult(): SearchResultData
    {
        return new SearchResultData(
            title: $this->title,
            url: '/' . ltrim($this->url, '/'),
            excerpt: $this->excerpt,
            type: $this->type,
            score: $this->score,
        );
    }
}
