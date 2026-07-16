<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static string run(string $query)
 */
final class NormalizeSearchQueryAction
{
    use AsFake;
    use AsObject;

    public function handle(string $query): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($query)));
    }
}
