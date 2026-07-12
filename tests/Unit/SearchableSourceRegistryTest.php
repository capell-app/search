<?php

declare(strict_types=1);

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Database\Eloquent\Model;

test('registry stores enabled source definitions keyed by source key', function (): void {
    $registry = new SearchableSourceRegistry;

    $registry->register(new SearchableSourceData(
        key: 'extensions',
        label: 'Extensions',
        modelClass: Model::class,
        type: 'extension',
        enabledSettingKey: 'sources.extensions.enabled',
        enabledByDefault: true,
        weight: 1.5,
    ));

    expect($registry->all())->toHaveCount(1)
        ->and($registry->enabled())->toHaveCount(1)
        ->and($registry->get('extensions')?->type)->toBe('extension');
});

test('registry replaces a source with the same key', function (): void {
    $registry = new SearchableSourceRegistry;

    $registry->register(new SearchableSourceData(
        key: 'extensions',
        label: 'Extensions',
        modelClass: Model::class,
        type: 'extension',
        enabledByDefault: true,
    ));

    $registry->register(new SearchableSourceData(
        key: 'extensions',
        label: 'Marketplace extensions',
        modelClass: Model::class,
        type: 'marketplace_extension',
        enabledByDefault: false,
    ));

    expect($registry->all())->toHaveCount(1)
        ->and($registry->get('extensions')?->label)->toBe('Marketplace extensions')
        ->and($registry->enabled())->toHaveCount(0);
});
