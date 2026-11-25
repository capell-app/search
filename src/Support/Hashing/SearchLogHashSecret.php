<?php

declare(strict_types=1);

namespace Capell\Search\Support\Hashing;

use RuntimeException;

/**
 * Resolves the secret that keys stored search log hashes (the normalised query and clicked result).
 *
 * The secret is only ever CAPELL_SEARCH_LOG_HASH_SECRET. It never falls back to APP_KEY, so rotating
 * the application key cannot silently re-key retained search history, and a leaked application key
 * does not also expose which search terms the hashes stand for. Without a secret, search logging is off.
 */
final class SearchLogHashSecret
{
    public const string ENVIRONMENT_KEY = 'CAPELL_SEARCH_LOG_HASH_SECRET';

    public static function configured(): bool
    {
        return self::resolve() !== null;
    }

    public static function current(): string
    {
        $secret = self::resolve();

        throw_if($secret === null, RuntimeException::class, sprintf('Search log hashing needs %s; it never falls back to APP_KEY.', self::ENVIRONMENT_KEY));

        return $secret;
    }

    /**
     * A short, non-reversible identifier for the secret that wrote a row, so rows written under an
     * earlier secret can be found and re-keyed without the secret itself being stored.
     */
    public static function keyId(string $secret): string
    {
        return substr(hash_hmac('sha256', 'capell-search-log-hash-key-id', $secret), 0, 16);
    }

    public static function currentKeyId(): string
    {
        return self::keyId(self::current());
    }

    private static function resolve(): ?string
    {
        $secret = config('capell-search.logs.hash_secret');

        return is_string($secret) && trim($secret) !== '' ? $secret : null;
    }
}
