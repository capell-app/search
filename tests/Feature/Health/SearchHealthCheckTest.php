<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Search\Actions\ProbeScoutIndexHealthAction;
use Capell\Search\Health\SearchHealthCheck;
use Capell\Search\Models\SearchLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('capell-search.logs.table_name', 'search_logs');
    config()->set('capell-search.minimum_query_length', 2);
    config()->set('capell-search.logs.retention_days', 180);
    config()->set('capell-search.driver', 'site_discovery');
    config()->set('scout.driver', 'collection');
    config()->set('capell-search.health.scout_indexes', []);

    Schema::dropIfExists('search_logs');
    Schema::create('search_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable();
        $table->foreignId('language_id')->nullable();
        $table->string('query');
        $table->string('normalized_query')->index();
        $table->unsignedInteger('results_count')->default(0);
        $table->string('clicked_result_url')->nullable();
        $table->string('ip_hash', 64)->nullable();
        $table->string('user_agent_hash', 64)->nullable();
        $table->timestamp('searched_at')->index();
        $table->timestamps();
    });

    CapellCore::registerModels([SearchLog::class]);
});

afterEach(function (): void {
    Schema::dropIfExists('search_logs');
});

it('reports compatible capell api version', function (): void {
    expect(SearchHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('runs diagnostics with runtime probes', function (): void {
    $results = SearchHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(6)
        ->and($results->pluck('label')->all())->toContain(
            'Search log storage table',
            'SearchLog model registration',
            'Search driver resolution',
            'Search log write probe',
            'Search logging configuration',
            'Scout index health',
        );
});

it('passes storage table, model registration, runtime probes, and logging config when valid', function (): void {
    $results = SearchHealthCheck::runDiagnostics();

    expect($results->pluck('passed')->all())->toBe([true, true, true, true, true, true])
        ->and(SearchLog::query()->where('query', '__capell_health_probe__')->exists())->toBeFalse();
});

it('skips Scout index probes when Typesense is not active', function (): void {
    config()->set('capell-search.health.scout_indexes', [
        'pages' => ['invalid'],
    ]);

    $result = (new SearchHealthCheck)->scoutIndexHealthCheck();

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toContain('skipped');
});

it('passes clearly when no Scout index probes are configured', function (): void {
    config()->set('capell-search.driver', 'scout');
    config()->set('scout.driver', 'typesense');

    $result = (new SearchHealthCheck)->scoutIndexHealthCheck();

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toContain('No Scout index health probes are configured');
});

it('passes configured Typesense index probes when counts and sample results match', function (): void {
    config()->set('capell-search.driver', 'scout');
    config()->set('scout.driver', 'typesense');
    config()->set('capell-search.health.scout_indexes', [
        'pages' => [
            'model' => SearchLog::class,
            'query' => 'health probe',
            'expected_model' => ['column' => 'id', 'value' => 42],
        ],
    ]);

    $action = Mockery::mock(ProbeScoutIndexHealthAction::class);
    $action->shouldReceive('handle')->once()->andReturn([
        'database_count' => 3,
        'index_count' => 3,
        'sample_matched' => true,
    ]);
    app()->instance(ProbeScoutIndexHealthAction::class, $action);

    $result = (new SearchHealthCheck)->scoutIndexHealthCheck();

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toContain('1 configured Scout index probe(s) passed');
});

it('reports missing indexes, count drift, and failed sample round trips', function (): void {
    config()->set('capell-search.driver', 'scout');
    config()->set('scout.driver', 'typesense');
    config()->set('capell-search.health.scout_indexes', [
        'missing' => [
            'model' => SearchLog::class,
            'query' => 'missing',
            'expected_model' => ['column' => 'id', 'value' => 1],
        ],
        'stale' => [
            'model' => SearchLog::class,
            'query' => 'stale',
            'expected_model' => ['column' => 'id', 'value' => 2],
        ],
    ]);

    $action = Mockery::mock(ProbeScoutIndexHealthAction::class);
    $action->shouldReceive('handle')->once()->andThrow(new RuntimeException('Not Found'));
    $action->shouldReceive('handle')->once()->andReturn([
        'database_count' => 4,
        'index_count' => 3,
        'sample_matched' => false,
    ]);
    app()->instance(ProbeScoutIndexHealthAction::class, $action);

    $result = (new SearchHealthCheck)->scoutIndexHealthCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toContain('missing index could not be queried')
        ->and($result->message)->toContain('stale document count differs')
        ->and($result->message)->toContain('stale sample query did not return the expected model')
        ->and($result->remediation)->not->toBeNull();
});

it('fails storage table check when table is missing', function (): void {
    Schema::dropIfExists('search_logs');

    $result = (new SearchHealthCheck)->storageTableCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->not->toBeNull();
});

it('fails logging configuration check when values are invalid', function (): void {
    config()->set('capell-search.minimum_query_length', 0);
    config()->set('capell-search.logs.retention_days', 0);

    $result = (new SearchHealthCheck)->loggingConfigurationCheck();

    expect($result->passed)->toBeFalse()
        ->and($result->remediation)->not->toBeNull();
});
