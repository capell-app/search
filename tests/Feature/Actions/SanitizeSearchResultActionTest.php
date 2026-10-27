<?php

declare(strict_types=1);

use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\ResolvePromotedSearchDestinationAction;
use Capell\Search\Actions\SanitizeSearchResultAction;
use Capell\Search\Data\SearchResultData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

test('unsafe config promotions remain excluded from public results', function (): void {
    config()->set('capell-search.promoted_results', [
        ['queries' => ['pricing'], 'title' => 'Private', 'url' => '/admin/users'],
        ['queries' => ['pricing'], 'title' => 'Unsupported', 'url' => 'javascript:alert(1)'],
        ['queries' => ['pricing'], 'title' => 'Public', 'url' => '/pricing'],
    ]);
    /** @var Collection<int, SearchResultData> $items */
    $items = collect();
    $results = ApplySearchResultEnhancementsAction::run(new LengthAwarePaginator($items, 0, 10, 1), 'pricing');
    expect(collect($results->items())->pluck('url')->all())->toBe(['/pricing']);
});

test('authoring uses the public destination policy including configured exclusions', function (): void {
    config()->set('capell-search.public_urls.blocked_path_prefixes', ['/private']);
    config()->set('capell-search.public_urls.strip_query_keys', ['secret']);

    expect(ResolvePromotedSearchDestinationAction::run('/private/offer')->publicUrl)->toBeNull();
    $destination = ResolvePromotedSearchDestinationAction::run('https://example.test/pricing?secret=hidden&plan=team');
    expect($destination->publicUrl)->toBe('https://example.test/pricing?plan=team')
        ->and($destination->changed)->toBeTrue()
        ->and(SanitizeSearchResultAction::run(new SearchResultData('Plan', '/private/offer', '')))->toBeNull();
});

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
