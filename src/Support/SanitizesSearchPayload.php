<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Illuminate\Support\Str;

trait SanitizesSearchPayload
{
    protected function cleanText(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' ', array_map(fn (mixed $item): string => $this->cleanText($item), $value));
        }

        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\b(admin|field_path|preview|signed|workspace_id|created_by|updated_by|deleted_by)\b\s*[:=]\s*\S+/i', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function excerpt(string $text, int $limit = 240): string
    {
        return Str::limit($this->cleanText($text), $limit);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function payload(
        int|string $id,
        string $title,
        string $url,
        string $excerpt,
        string $body,
        string $type,
        ?int $siteId = null,
        ?int $languageId = null,
        ?int $updatedAt = null,
        array $meta = [],
    ): array {
        $safeMeta = $this->safeMeta($meta);

        return [
            'id' => (string) $id,
            'title' => $this->cleanText($title),
            'url' => $url,
            'excerpt' => $this->cleanText($excerpt),
            'body' => $this->cleanText($body),
            'type' => $type,
            'site_id' => $siteId,
            'language_id' => $languageId,
            'updated_at' => $updatedAt ?? now()->timestamp,
            'meta' => $safeMeta,
        ] + collect($safeMeta)
            ->mapWithKeys(static fn (mixed $value, string $key): array => ['meta_' . $key => $value])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function safeMeta(array $meta): array
    {
        return collect($meta)
            ->mapWithKeys(function (mixed $value, string $key): array {
                if (is_array($value)) {
                    $value = implode(', ', array_map(fn (mixed $item): string => $this->cleanText($item), $value));
                }

                return [$key => is_scalar($value) ? $this->cleanText((string) $value) : null];
            })
            ->filter(static fn (mixed $value, string $key): bool => is_string($value)
                && $value !== ''
                && ! str($key)->lower()->contains(['admin', 'editor', 'model', 'signed', 'token', 'preview', 'field']))
            ->all();
    }
}
