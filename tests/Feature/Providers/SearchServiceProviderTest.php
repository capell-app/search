<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionManagementSurfaceRegistry;
use Capell\Core\Support\Settings\SettingsSchemaRegistry;
use Capell\Search\Contracts\Search;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Search\Settings\SearchSettings;

test('provider binds the configured database search driver', function (): void {
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.driver', SearchDriver::Database->value);

    expect(resolve(Search::class))->toBeInstanceOf(DatabaseSearch::class);
});

test('provider prefers the settings driver over config', function (): void {
    $settings = new SearchSettings;
    $settings->driver = SearchDriver::Database;

    app()->instance(SearchSettings::class, $settings);
    app()->register(SearchServiceProvider::class);

    config()->set('capell-search.driver', SearchDriver::Scout->value);

    expect(resolve(Search::class))->toBeInstanceOf(DatabaseSearch::class);
});

test('site search settings expose defaults', function (): void {
    expect(new SearchSettings)
        ->enabled->toBeTrue()
        ->show_header_search->toBeTrue()
        ->results_per_page->toBe(10)
        ->driver->toBe(SearchDriver::Database)
        ->record_search_logs->toBeTrue()
        ->log_retention_days->toBe(180)
        ->hash_visitor_data->toBeTrue()
        ->minimum_query_length->toBe(2);
});

test('search driver options expose translated filament labels', function (): void {
    expect(SearchDriver::Database->getLabel())->toBe('Database')
        ->and(SearchDriver::Scout->getLabel())->toBe('Scout');
});

test('provider registers search settings metadata and schema', function (): void {
    $registry = resolve(SettingsSchemaRegistry::class);

    expect($registry->getSettingsClass('search'))->toBe(SearchSettings::class)
        ->and($registry->getSchema('search', 'SearchSettingsSchema'))->toBe(SearchSettingsSchema::class)
        ->and($registry->getMetadata('search')?->getLabel())->toBe('Search settings')
        ->and($registry->getMetadata('search')?->packageName)->toBe(SearchServiceProvider::$packageName);
});

test('provider registers search settings as an extension management surface', function (): void {
    $settingsSurfaces = resolve(ExtensionManagementSurfaceRegistry::class)
        ->surfacesForPackage(SearchServiceProvider::$packageName);

    expect($settingsSurfaces[0]->settingsGroup ?? null)->toBe('search');
});
