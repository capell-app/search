<?php

declare(strict_types=1);

use Capell\Search\Actions\ResolveSearchResultTypeLabelAction;
use Capell\Search\Providers\SearchServiceProvider;

beforeEach(function (): void {
    app()->register(SearchServiceProvider::class);
});

test('configured type labels override translated defaults', function (): void {
    config()->set('capell-search.type_labels', [
        'page' => 'Landing page',
    ]);

    expect(ResolveSearchResultTypeLabelAction::run('page'))->toBe('Landing page');
});

test('known type labels resolve through translations', function (): void {
    config()->set('capell-search.type_labels', []);

    expect(ResolveSearchResultTypeLabelAction::run('page'))->toBe('Page');
});

test('unknown type labels fall back to headline formatting', function (): void {
    config()->set('capell-search.type_labels', []);

    expect(ResolveSearchResultTypeLabelAction::run('knowledge-base_article'))->toBe('Knowledge Base Article');
});
