<?php

declare(strict_types=1);

use Capell\Search\Actions\FlushScoutSearchSourcesAction;
use Capell\Search\Actions\IndexScoutSearchSourcesAction;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Capell\Search\Tests\Fixtures\SearchMaintenanceScoutModel;
use Capell\Search\Tests\Fixtures\SearchPlainModel;

beforeEach(function (): void {
    SearchMaintenanceScoutModel::resetMaintenanceState();
});

test('indexes and flushes enabled scout searchable sources', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'pages',
        label: 'Pages',
        modelClass: SearchMaintenanceScoutModel::class,
        type: 'page',
    ));
    $registry->register(new SearchableSourceData(
        key: 'plain',
        label: 'Plain',
        modelClass: SearchPlainModel::class,
        type: 'plain',
    ));

    expect((new IndexScoutSearchSourcesAction($registry))->handle(chunk: 250))->toBe(['pages'])
        ->and(SearchMaintenanceScoutModel::$indexCalls)->toBe(1)
        ->and(SearchMaintenanceScoutModel::$indexedChunk)->toBe(250)
        ->and((new FlushScoutSearchSourcesAction($registry))->handle())->toBe(['pages'])
        ->and(SearchMaintenanceScoutModel::$flushCalls)->toBe(1);
});

test('limits scout maintenance to a requested source key', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'pages',
        label: 'Pages',
        modelClass: SearchMaintenanceScoutModel::class,
        type: 'page',
    ));

    expect((new IndexScoutSearchSourcesAction($registry))->handle(sourceKey: 'missing'))->toBe([])
        ->and(SearchMaintenanceScoutModel::$indexCalls)->toBe(0)
        ->and((new FlushScoutSearchSourcesAction($registry))->handle(sourceKey: 'pages'))->toBe(['pages'])
        ->and(SearchMaintenanceScoutModel::$flushCalls)->toBe(1);
});
