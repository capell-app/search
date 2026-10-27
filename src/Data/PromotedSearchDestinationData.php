<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class PromotedSearchDestinationData extends Data
{
    public function __construct(
        public readonly ?string $publicUrl,
        public readonly bool $changed,
    ) {}
}
