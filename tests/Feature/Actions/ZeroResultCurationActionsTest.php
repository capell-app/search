<?php

declare(strict_types=1);

use Capell\Search\Actions\CreatePromotedResultFromZeroResultSearchAction;
use Capell\Search\Actions\CreateSynonymFromZeroResultSearchAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Settings\SearchSettings;

beforeEach(function (): void {
    $settings = new SearchSettings;
    $settings->synonyms = [
        'cms' => ['content management system'],
    ];
    $settings->promoted_results = [
        [
            'queries' => ['pricing'],
            'title' => 'Pricing',
            'url' => '/pricing',
            'excerpt' => 'Plan information.',
            'type' => 'page',
            'score' => 1000.0,
        ],
    ];

    app()->instance(SearchSettings::class, $settings);
});

test('creates a synonym from a zero-result query', function (): void {
    $synonyms = CreateSynonymFromZeroResultSearchAction::run('Capel CMS', 'Capell CMS');

    expect($synonyms)->toHaveKey('capell cms')
        ->and($synonyms['capell cms'])->toBe(['capel cms'])
        ->and(ResolveExpandedSearchQueriesAction::run('capel cms'))->toContain('capell cms');
});

test('does not duplicate zero-result synonym aliases', function (): void {
    CreateSynonymFromZeroResultSearchAction::run('Capel CMS', 'Capell CMS');
    $synonyms = CreateSynonymFromZeroResultSearchAction::run('capel cms', 'capell cms');

    expect($synonyms['capell cms'])->toBe(['capel cms']);
});

test('creates a promoted result from a zero-result query', function (): void {
    $promotions = CreatePromotedResultFromZeroResultSearchAction::run(
        zeroResultQuery: 'Implementation help',
        title: 'Implementation services',
        url: '/services/implementation',
        excerpt: 'Talk to the implementation team.',
        type: 'service',
        score: 1200.0,
    );

    expect($promotions)->toHaveCount(2)
        ->and($promotions[1])->toMatchArray([
            'queries' => ['implementation help'],
            'title' => 'Implementation services',
            'url' => '/services/implementation',
            'excerpt' => 'Talk to the implementation team.',
            'type' => 'service',
            'score' => 1200.0,
        ])
        ->and(ResolvePromotedSearchResultsAction::run('implementation help'))->toHaveCount(1)
        ->and(ResolvePromotedSearchResultsAction::run('implementation help')[0]->title)->toBe('Implementation services');
});

test('replaces duplicate promoted results for the same zero-result query and url', function (): void {
    CreatePromotedResultFromZeroResultSearchAction::run('Implementation help', 'Old title', '/services/implementation');
    $promotions = CreatePromotedResultFromZeroResultSearchAction::run('implementation help', 'New title', '/services/implementation');

    expect($promotions)->toHaveCount(2)
        ->and($promotions[1]['title'])->toBe('New title');
});
