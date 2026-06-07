<?php

declare(strict_types=1);

use Capell\Search\Actions\SanitizeSearchResultAction;
use Capell\Search\Data\SearchResultData;

test('search result sanitization only keeps explicitly public meta keys', function (): void {
    $result = SanitizeSearchResultAction::run(new SearchResultData(
        title: 'Result',
        url: '/docs/result',
        excerpt: 'Result excerpt.',
        meta: [
            'author' => 'Ben',
            'tags' => ['cms', 'search'],
            'description' => 'Public description',
            'internal_ref' => 'entry-123',
            'owner_id' => 42,
            'adminUrl' => '/admin/pages/42',
            'preview_token' => 'secret',
        ],
    ));

    expect($result?->meta)->toBe([
        'author' => 'Ben',
        'tags' => ['cms', 'search'],
        'description' => 'Public description',
    ]);
});

test('search result sanitization drops nested meta payloads even for allowed keys', function (): void {
    $result = SanitizeSearchResultAction::run(new SearchResultData(
        title: 'Result',
        url: '/docs/result',
        excerpt: 'Result excerpt.',
        meta: [
            'source' => [
                'label' => 'Docs',
                'model_id' => 42,
            ],
            'category' => 'Docs',
        ],
    ));

    expect($result?->meta)->toBe([
        'category' => 'Docs',
    ]);
});

test('search result sanitization supports configured public meta keys', function (): void {
    config()->set('capell-search.public_urls.allowed_meta_keys', [
        'reading_time',
    ]);

    $result = SanitizeSearchResultAction::run(new SearchResultData(
        title: 'Result',
        url: '/docs/result',
        excerpt: 'Result excerpt.',
        meta: [
            'reading_time' => '4 min',
            'author' => 'Ben',
        ],
    ));

    expect($result?->meta)->toBe([
        'reading_time' => '4 min',
    ]);
});
