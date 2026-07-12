<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class HashSearchRetentionValueAction
{
    use AsAction;

    public function handle(string $value): string
    {
        $secret = config('capell-search.logs.hash_secret');
        $secret = is_string($secret) && $secret !== '' ? $secret : config('app.key');
        throw_unless(is_string($secret) && $secret !== '', RuntimeException::class, 'Search retention hash secret is unavailable.');

        return hash_hmac('sha256', $value, $secret);
    }
}
