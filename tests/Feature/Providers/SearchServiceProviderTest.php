<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionManagementSurfaceRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\CapellCoreManager;
use Capell\Core\Support\Settings\SettingsSchemaRegistry;
use Capell\Search\Contracts\Search;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Drivers\SiteDiscoverySearch;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Capell\Search\Models\SearchLog;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Search\Settings\SearchSettings;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Database\Eloquent\Model;

test('provider binds site discovery as the default search driver', function (): void {
    app()->register(SearchServiceProvider::class);

    expect(resolve(Search::class))->toBeInstanceOf(SiteDiscoverySearch::class);
});

test('provider binds the configured database search driver', function (): void {
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.driver', SearchDriver::Database->value);

    expect(resolve(Search::class))->toBeInstanceOf(DatabaseSearch::class);
});

test('provider binds the site discovery search driver', function (): void {
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.driver', SearchDriver::SiteDiscovery->value);

    expect(resolve(Search::class))->toBeInstanceOf(SiteDiscoverySearch::class);
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
        ->driver->toBe(SearchDriver::SiteDiscovery)
        ->record_search_logs->toBeTrue()
        ->log_retention_days->toBe(180)
        ->hash_visitor_data->toBeTrue()
        ->minimum_query_length->toBe(2);
});

test('search driver options expose translated filament labels', function (): void {
    expect(SearchDriver::Database->getLabel())->toBe('Database')
        ->and(SearchDriver::SiteDiscovery->getLabel())->toBe('Site Discovery URL registry')
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

test('provider registers installed surfaces during the package boot lifecycle', function (): void {
    $core = CapellCore::getFacadeRoot();

    expect($core)->toBeInstanceOf(CapellCoreManager::class);
    assert($core instanceof CapellCoreManager);

    $models = array_filter(
        $core->getModels(),
        static fn (string $model): bool => $model !== SearchLog::class,
    );

    $modelsProperty = new ReflectionProperty($core, 'models');
    $modelsProperty->setValue($core, $models);

    CapellCore::forcePackageInstalled(SearchServiceProvider::$packageName, false);

    $provider = app()->getProvider(SearchServiceProvider::class);

    expect($provider)
        ->toBeInstanceOf(SearchServiceProvider::class)
        ->and(CapellCore::isPackageInstalled(SearchServiceProvider::$packageName))->toBeFalse();
    assert($provider instanceof SearchServiceProvider);

    $provider->packageRegistered();

    expect(CapellCore::getModels())->not->toContain(SearchLog::class);

    CapellCore::forcePackageInstalled(SearchServiceProvider::$packageName);

    expect(CapellCore::isPackageInstalled(SearchServiceProvider::$packageName))->toBeTrue();

    $provider->packageBooted();

    expect(CapellCore::getModels())->toContain(SearchLog::class);
});

test('provider registers configured searchable sources', function (): void {
    config()->set('capell-search.searchables', [
        'extensions' => [
            'label' => 'Extensions',
            'model' => Model::class,
            'type' => 'extension',
            'enabled' => true,
            'weight' => 2.0,
        ],
    ]);

    app()->register(SearchServiceProvider::class);

    /** @var SearchableSourceRegistry $registry */
    $registry = resolve(SearchableSourceRegistry::class);

    expect($registry->get('extensions')?->type)->toBe('extension')
        ->and($registry->get('extensions')?->weight)->toBe(2.0);
});
