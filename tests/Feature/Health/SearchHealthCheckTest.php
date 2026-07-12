<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Search\Health\SearchHealthCheck;
use Capell\Search\Models\SearchLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('capell-search.logs.table_name', 'search_logs');
    config()->set('capell-search.minimum_query_length', 2);
    config()->set('capell-search.logs.retention_days', 180);

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
    expect(SearchHealthCheck::compatibleCapellApiVersion())->toBe('^0.0');
});

it('runs diagnostics with runtime probes', function (): void {
    $results = SearchHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(5)
        ->and($results->pluck('label')->all())->toContain(
            'Search log storage table',
            'SearchLog model registration',
            'Search driver resolution',
            'Search log write probe',
            'Search logging configuration',
        );
});

it('passes storage table, model registration, runtime probes, and logging config when valid', function (): void {
    $results = SearchHealthCheck::runDiagnostics();

    expect($results->pluck('passed')->all())->toBe([true, true, true, true, true])
        ->and(SearchLog::query()->where('query', '__capell_health_probe__')->exists())->toBeFalse();
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
