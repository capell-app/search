<?php

declare(strict_types=1);

use Capell\Search\Actions\ResolveCorrectedSearchQueryAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Settings\SearchSettings;

test('search curation actions read persisted settings before config fallbacks', function (): void {
    config()->set('capell-search.synonyms', []);
    config()->set('capell-search.typo_corrections', []);
    config()->set('capell-search.typo_terms', []);
    config()->set('capell-search.promoted_results', []);

    $settings = new SearchSettings;
    $settings->synonyms = [
        'cms' => 'content management system',
    ];
    $settings->typo_corrections = [
        'capel' => 'capell',
    ];
    $settings->typo_terms = [
        'marketplace',
    ];
    $settings->typo_max_distance = 2;
    $settings->promoted_results = [
        [
            'queries' => ['capell'],
            'title' => 'Capell best bet',
            'url' => '/capell-best-bet',
            'excerpt' => 'Pinned Capell result',
            'type' => 'page',
            'score' => 900.0,
        ],
    ];

    app()->instance(SearchSettings::class, $settings);

    expect(ResolveExpandedSearchQueriesAction::run('cms hosting'))->toContain('content management system hosting')
        ->and(ResolveCorrectedSearchQueryAction::run('capel cms'))->toBe('capell cms')
        ->and(ResolveCorrectedSearchQueryAction::run('marketplce guide'))->toBe('marketplace guide')
        ->and(ResolvePromotedSearchResultsAction::run('capell'))->toHaveCount(1)
        ->and(ResolvePromotedSearchResultsAction::run('capell')[0]->title)->toBe('Capell best bet');
});
