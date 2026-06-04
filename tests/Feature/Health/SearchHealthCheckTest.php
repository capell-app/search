<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
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

it('reports a compatible capell api version', function (): void {
    expect(SearchHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = SearchHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(3)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when the storage table, model registration, and logging config are valid', function (): void {
    expect(CapellCore::getModels())->toContain(SearchLog::class)
        ->and(SearchHealthCheck::passed())->toBeTrue()
        ->and(SearchHealthCheck::runDiagnostics()->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue();
});

it('fails when the search log table is missing', function (): void {
    Schema::drop('search_logs');

    $check = new SearchHealthCheck;

    expect($check->hasSearchLogTable())->toBeFalse()
        ->and($check->storageTableCheck()->passed)->toBeFalse()
        ->and(SearchHealthCheck::passed())->toBeFalse();
});

it('fails when logging configuration cannot support retention', function (): void {
    config()->set('capell-search.logs.retention_days', 0);

    $check = new SearchHealthCheck;

    expect($check->hasValidLoggingConfiguration())->toBeFalse()
        ->and($check->loggingConfigurationCheck()->passed)->toBeFalse()
        ->and(SearchHealthCheck::passed())->toBeFalse();
});
