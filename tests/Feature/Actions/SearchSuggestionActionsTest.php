<?php

declare(strict_types=1);

use Capell\Search\Actions\BuildAutocompleteQuerySuggestionsAction;
use Capell\Search\Actions\ResolveCorrectedSearchQueryAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Models\SearchLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('capell-search.logs.table_name', 'search_logs');

    Schema::dropIfExists('search_logs');
    Schema::create('search_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->index();
        $table->foreignId('language_id')->nullable()->index();
        $table->string('query');
        $table->string('normalized_query')->index();
        $table->unsignedInteger('results_count')->default(0);
        $table->string('clicked_result_url')->nullable();
        $table->string('ip_hash', 64)->nullable();
        $table->string('user_agent_hash', 64)->nullable();
        $table->timestamp('searched_at')->index();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('search_logs');
});

test('resolves explicit and dictionary corrected search queries', function (): void {
    config()->set('capell-search.typo_corrections', [
        'capel' => 'capell',
    ]);
    config()->set('capell-search.typo_terms', [
        'marketplace',
    ]);

    expect(ResolveCorrectedSearchQueryAction::run('capel cms'))->toBe('capell cms')
        ->and(ResolveCorrectedSearchQueryAction::run('marketplce extensions'))->toBe('marketplace extensions')
        ->and(ResolveCorrectedSearchQueryAction::run('capell cms'))->toBeNull();
});

test('suggests curated terms for a three letter prefix and never a hash or logged query', function (): void {
    config()->set('capell-search.synonyms', ['cms' => ['content management system']]);
    config()->set('capell-search.promoted_results', [['queries' => ['pricing plans'], 'title' => 'x', 'url' => '/p']]);
    SearchLog::query()->insert([[
        'site_id' => 10, 'language_id' => 20, 'query' => 'secret person', 'normalized_query' => str_repeat('a', 64),
        'results_count' => 1, 'searched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]]);

    $content = collect(BuildAutocompleteQuerySuggestionsAction::run('con', siteId: 10, languageId: 20))->pluck('query')->all();
    $pricing = collect(BuildAutocompleteQuerySuggestionsAction::run('pri', siteId: 10, languageId: 20))->pluck('query')->all();
    $hash = BuildAutocompleteQuerySuggestionsAction::run('aaa', siteId: 10, languageId: 20);

    expect($content)->toBe(['content management system'])
        ->and($pricing)->toBe(['pricing plans'])
        ->and($hash)->toBe([]);
});

test('suggestions and promotions respect the site and language they are configured for', function (): void {
    config()->set('capell-search.synonyms', []);
    config()->set('capell-search.site_synonyms', [10 => ['pricing' => ['pricing for agencies']], 20 => ['pricing' => ['pricing for schools']]]);
    config()->set('capell-search.promoted_results', [
        ['queries' => ['pricing special'], 'title' => 'Agency deal', 'url' => '/agency', 'site_ids' => [10]],
        ['queries' => ['pricing everywhere'], 'title' => 'All sites', 'url' => '/all'],
        ['queries' => ['pricing french'], 'title' => 'FR', 'url' => '/fr', 'language_ids' => [2]],
    ]);

    $siteTen = collect(BuildAutocompleteQuerySuggestionsAction::run('pri', siteId: 10, languageId: 1))->pluck('query')->all();
    $siteTwenty = collect(BuildAutocompleteQuerySuggestionsAction::run('pri', siteId: 20, languageId: 2))->pluck('query')->all();

    expect($siteTen)->toContain('pricing for agencies', 'pricing special', 'pricing everywhere')
        ->not->toContain('pricing for schools', 'pricing french')
        ->and($siteTwenty)->toContain('pricing for schools', 'pricing everywhere', 'pricing french')
        ->not->toContain('pricing for agencies', 'pricing special')
        ->and(ResolvePromotedSearchResultsAction::run('pricing special', 20, 2))->toBe([])
        ->and(ResolvePromotedSearchResultsAction::run('pricing special', 10, 1))->toHaveCount(1);
});
