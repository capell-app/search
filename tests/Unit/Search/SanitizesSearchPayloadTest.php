<?php

declare(strict_types=1);

use Capell\Search\Support\SanitizesSearchPayload;

/**
 * Test double exposing the protected sanitizer methods.
 */
$sanitizer = new class
{
    use SanitizesSearchPayload;

    public function clean(mixed $value): string
    {
        return $this->cleanText($value);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function build(array $meta = []): array
    {
        return $this->payload(
            id: 42,
            title: '<b>Title</b>',
            url: 'https://example.test/x',
            excerpt: 'Excerpt',
            body: 'Body',
            type: 'page',
            meta: $meta,
        );
    }
};

test('cleanText strips script and style blocks', function () use ($sanitizer): void {
    expect($sanitizer->clean('Hello <script>alert(1)</script><style>.x{}</style> world'))
        ->toBe('Hello world');
});

test('cleanText removes editor leakage key/value pairs', function () use ($sanitizer): void {
    $cleaned = $sanitizer->clean('Safe field_path=blocks.0.text signed=abc workspace_id=7 copy');

    expect($cleaned)
        ->not->toContain('field_path')
        ->and($cleaned)->not->toContain('signed')
        ->and($cleaned)->not->toContain('workspace_id');
});

test('safeMeta drops admin/editor/model/signed/token/preview/field keys', function () use ($sanitizer): void {
    $payload = $sanitizer->build([
        'severity' => 'high',
        'admin_note' => 'secret',
        'signed_url' => 'https://signed',
        'model_id' => '99',
        'field_path' => 'blocks.0',
    ]);

    expect($payload['meta'])
        ->toHaveKey('severity')
        ->not->toHaveKey('admin_note')
        ->not->toHaveKey('signed_url')
        ->not->toHaveKey('model_id')
        ->not->toHaveKey('field_path');
});

test('payload normalises id to string and flattens meta', function () use ($sanitizer): void {
    $payload = $sanitizer->build(['severity' => 'high']);

    expect($payload['id'])->toBe('42')
        ->and($payload['title'])->toBe('Title')
        ->and($payload['meta_severity'])->toBe('high');
});
