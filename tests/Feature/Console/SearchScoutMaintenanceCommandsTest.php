<?php

declare(strict_types=1);

use Capell\Search\Support\SearchableSourceRegistry;
use Capell\Search\Tests\Fixtures\SearchMaintenanceScoutModel;
use Illuminate\Console\Command;

beforeEach(function (): void {
    SearchMaintenanceScoutModel::resetMaintenanceState();
    config()->set('capell-search.searchables', [
        'pages' => [
            'label' => 'Pages',
            'model' => SearchMaintenanceScoutModel::class,
            'type' => 'page',
            'enabled' => true,
        ],
    ]);

    app()->forgetInstance(SearchableSourceRegistry::class);
});

test('search index command imports configured scout sources', function (): void {
    $this->artisan('search:index', ['--chunk' => '100'])
        ->expectsOutput('Indexed 1 search source: pages.')
        ->assertExitCode(Command::SUCCESS);

    expect(SearchMaintenanceScoutModel::$indexCalls)->toBe(1)
        ->and(SearchMaintenanceScoutModel::$indexedChunk)->toBe(100);
});

test('search flush command flushes configured scout sources', function (): void {
    $this->artisan('search:flush', ['--source' => 'pages'])
        ->expectsOutput('Flushed 1 search source index: pages.')
        ->assertExitCode(Command::SUCCESS);

    expect(SearchMaintenanceScoutModel::$flushCalls)->toBe(1);
});

test('search index command rejects invalid chunks before indexing', function (string $chunk): void {
    $this->artisan('search:index', ['--chunk' => $chunk])
        ->expectsOutput('The --chunk option must be a positive integer.')
        ->assertExitCode(Command::FAILURE);

    expect(SearchMaintenanceScoutModel::$indexCalls)->toBe(0);
})->with(['abc', '0', '-1']);

test('search maintenance commands fail when no matching scout source is available', function (): void {
    $this->artisan('search:index', ['--source' => 'missing'])
        ->expectsOutput('No indexable Scout search sources were found.')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('search:flush', ['--source' => 'missing'])
        ->expectsOutput('No flushable Scout search sources were found.')
        ->assertExitCode(Command::FAILURE);
});
