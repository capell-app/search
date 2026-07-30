<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Capell\Core\Data\Database\SqlFragment;

final readonly class DatabaseFullTextSearch
{
    public function __construct(
        public SqlFragment $predicate,
        public SqlFragment $relevance,
        public bool $native,
    ) {}
}
