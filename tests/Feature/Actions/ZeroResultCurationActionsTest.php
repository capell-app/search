<?php

declare(strict_types=1);

use Capell\Search\Actions\CreatePromotedResultFromZeroResultSearchAction;
use Capell\Search\Actions\CreateSynonymFromZeroResultSearchAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Settings\SearchSettings;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedSearchSetting('enabled', true);
    seedSearchSetting('show_header_search', true);
    seedSearchSetting('results_per_page', 10);
    seedSearchSetting('driver', SearchDriver::SiteDiscovery->value);
    seedSearchSetting('record_search_logs', true);
    seedSearchSetting('log_retention_days', 180);
    seedSearchSetting('hash_visitor_data', true);
    seedSearchSetting('minimum_query_length', 2);
    seedSearchSetting('sources', []);
    seedSearchSetting('synonyms', [
        'cms' => ['content management system'],
    ]);
    seedSearchSetting('typo_corrections', []);
    seedSearchSetting('typo_terms', []);
    seedSearchSetting('typo_max_distance', 1);
    seedSearchSetting('promoted_results', [
        [
            'queries' => ['pricing'],
            'title' => 'Pricing',
            'url' => '/pricing',
            'excerpt' => 'Plan information.',
            'type' => 'page',
            'score' => 1000.0,
        ],
    ]);

    app()->forgetInstance(SearchSettings::class);
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

test('zero-result curation save failures are not swallowed', function (string $actionClass): void {
    throw_unless(class_exists($actionClass), RuntimeException::class, 'Expected a valid curation action class.');
    $source = file_get_contents((string) (new ReflectionClass($actionClass))->getFileName());

    expect($source)->toBeString()
        ->and($source)->toContain('->save();')
        ->and($source)->not->toContain('catch (Throwable)')
        ->and($source)->not->toContain('catch (\\Throwable)')
        ->and($source)->not->toContain('catch (Exception)')
        ->and($source)->not->toContain('catch (\\Exception)');
})->with([
    'synonym curation' => [CreateSynonymFromZeroResultSearchAction::class],
    'promoted-result curation' => [CreatePromotedResultFromZeroResultSearchAction::class],
]);

function seedSearchSetting(string $name, mixed $payload): void
{
    DB::table('settings')->updateOrInsert([
        'group' => SearchSettings::group(),
        'name' => $name,
    ], [
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'locked' => false,
    ]);
}
