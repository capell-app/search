<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * Data object representing a single search hit.
 */
final class SearchResultData extends Data
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $excerpt,
        public readonly string $type = 'page',
        public readonly float $score = 0.0,
        public readonly ?string $typeLabel = null,
        public readonly ?string $sourceKey = null,
        public readonly ?CarbonInterface $updatedAt = null,
        public readonly array $meta = [],
        public readonly bool $promoted = false,
    ) {}
}
