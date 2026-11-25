<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Support\Hashing\SearchLogHashSecret;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Keys a retained search value (normalised query or clicked result path) with the search log hash
 * secret. Callers check SearchLogHashSecret::configured() first: without the secret nothing is hashed.
 *
 * @method static string run(string $value, ?string $secret = null)
 */
final class HashSearchRetentionValueAction
{
    use AsFake;
    use AsObject;

    public function handle(string $value, ?string $secret = null): string
    {
        return hash_hmac('sha256', $value, $secret ?? SearchLogHashSecret::current());
    }
}
