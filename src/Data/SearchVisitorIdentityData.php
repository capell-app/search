<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Spatie\LaravelData\Data;

final class SearchVisitorIdentityData extends Data
{
    public function __construct(
        public readonly ?string $ipHash,
        public readonly ?string $userAgentHash,
    ) {}
}
