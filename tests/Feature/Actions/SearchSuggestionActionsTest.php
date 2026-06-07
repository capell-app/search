<?php

declare(strict_types=1);

use Capell\Search\Actions\BuildAutocompleteQuerySuggestionsAction;
use Capell\Search\Actions\ResolveCorrectedSearchQueryAction;
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

test('builds popular autocomplete query suggestions scoped by site and language', function (): void {
    SearchLog::query()->insert([
        [
            'site_id' => 10,
            'language_id' => 20,
            'query' => 'Capell marketplace',
            'normalized_query' => 'capell marketplace',
            'results_count' => 3,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'site_id' => 10,
            'language_id' => 20,
            'query' => 'Capell marketplace',
            'normalized_query' => 'capell marketplace',
            'results_count' => 2,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'site_id' => 10,
            'language_id' => 20,
            'query' => 'Capell migration',
            'normalized_query' => 'capell migration',
            'results_count' => 1,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'site_id' => 99,
            'language_id' => 20,
            'query' => 'Capell marketplace other site',
            'normalized_query' => 'capell marketplace other site',
            'results_count' => 1,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'site_id' => 10,
            'language_id' => 20,
            'query' => 'Capell',
            'normalized_query' => 'capell',
            'results_count' => 1,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $suggestions = BuildAutocompleteQuerySuggestionsAction::run('capell', siteId: 10, languageId: 20);

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]->query)->toBe('capell marketplace')
        ->and($suggestions[0]->searches)->toBe(2)
        ->and($suggestions[0]->url)->toBe(route('capell-frontend.search', ['q' => 'capell marketplace']))
        ->and($suggestions[1]->query)->toBe('capell migration');
});
