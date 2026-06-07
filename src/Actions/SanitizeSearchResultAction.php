<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchResultData;
use Lorisleiva\Actions\Concerns\AsAction;

final class SanitizeSearchResultAction
{
    use AsAction;

    public function handle(SearchResultData $result): ?SearchResultData
    {
        $url = $this->sanitizeUrl($result->url);

        if ($url === null) {
            return null;
        }

        return new SearchResultData(
            title: trim(strip_tags($result->title)),
            url: $url,
            excerpt: trim(strip_tags($result->excerpt)),
            type: $result->type,
            score: $result->score,
            typeLabel: $result->typeLabel,
            sourceKey: $result->sourceKey,
            updatedAt: $result->updatedAt,
            meta: $this->safeMeta($result->meta),
            promoted: $result->promoted,
        );
    }

    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $allowedSchemes = config('capell-search.public_urls.allowed_schemes', ['', 'http', 'https']);

        if (! is_array($allowedSchemes) || ! in_array($scheme, $allowedSchemes, true)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? ($scheme === '' ? $url : '/'));

        foreach ($this->blockedPathPrefixes() as $prefix) {
            if (str_starts_with('/' . ltrim($path, '/'), '/' . ltrim($prefix, '/'))) {
                return null;
            }
        }

        if ($scheme === '') {
            $path = '/' . ltrim($path, '/');
            $query = $this->safeQuery((string) ($parts['query'] ?? ''));

            return $query === '' ? $path : $path . '?' . $query;
        }

        $host = (string) ($parts['host'] ?? '');

        if ($host === '') {
            return null;
        }

        $authority = $host;

        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $query = $this->safeQuery((string) ($parts['query'] ?? ''));

        return $scheme . '://' . $authority . $path . ($query === '' ? '' : '?' . $query);
    }

    /**
     * @return list<string>
     */
    private function blockedPathPrefixes(): array
    {
        $prefixes = config('capell-search.public_urls.blocked_path_prefixes', []);

        if (! is_array($prefixes)) {
            return [];
        }

        return array_values(collect($prefixes)
            ->map(static fn (mixed $prefix): string => is_string($prefix) ? trim($prefix) : '')
            ->filter()
            ->values()
            ->all());
    }

    private function safeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $parameters);

        foreach ($this->stripQueryKeys() as $key) {
            unset($parameters[$key]);
        }

        return http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function stripQueryKeys(): array
    {
        $keys = config('capell-search.public_urls.strip_query_keys', []);

        if (! is_array($keys)) {
            return [];
        }

        return array_values(collect($keys)
            ->map(static fn (mixed $key): string => is_string($key) ? $key : '')
            ->filter()
            ->values()
            ->all());
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function safeMeta(array $meta): array
    {
        $allowedKeys = $this->allowedMetaKeys();

        return collect($meta)
            ->filter(fn (mixed $value, string $key): bool => in_array(str($key)->lower()->toString(), $allowedKeys, true)
                && $this->isSafeMetaValue($value))
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allowedMetaKeys(): array
    {
        $keys = config('capell-search.public_urls.allowed_meta_keys', []);

        if (! is_array($keys)) {
            return [];
        }

        return array_values(collect($keys)
            ->map(static fn (mixed $key): string => is_string($key) ? str($key)->lower()->toString() : '')
            ->filter()
            ->unique()
            ->values()
            ->all());
    }

    private function isSafeMetaValue(mixed $value): bool
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        if (! array_is_list($value)) {
            return false;
        }

        return collect($value)->every(static fn (mixed $item): bool => $item === null || is_scalar($item));
    }
}
