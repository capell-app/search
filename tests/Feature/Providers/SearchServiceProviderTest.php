<?php

declare(strict_types=1);

use Capell\Admin\Support\Extensions\ExtensionManagementSurfaceRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Support\Settings\SettingsSchemaRegistry;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Capell\Search\Models\SearchLog;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Search\Settings\SearchSettings;
use Capell\Search\Support\SearchableSourceRegistry;
use Capell\Search\Tests\Fixtures\ProviderSearchUrlContributor;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

function searchProviderSources(): void
{
    Schema::create('provider_search_entries', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('status');
        $table->text('excerpt');
        $table->text('body');
    });
    DB::table('provider_search_entries')->insert([
        'title' => 'Provider marker database',
        'slug' => 'database-marker',
        'status' => 'published',
        'excerpt' => '',
        'body' => '',
    ]);
    config()->set('capell-search.database.table', 'provider_search_entries');
    $language = Language::factory()->english()->create(['status' => true]);
    $site = Site::factory()->withTranslations($language)->create(['status' => true]);
    app()->instance(ProviderSearchUrlContributor::class, new ProviderSearchUrlContributor($site, $language));
    app()->tag(ProviderSearchUrlContributor::class, PublicUrlContributor::TAG);
}

/** @return list<string> */
function searchProviderResultTitles(): array
{
    return array_values(array_map(
        static fn (SearchResultData $result): string => $result->title,
        resolve(Search::class)->search('Provider marker')->items(),
    ));
}

test('provider returns site discovery as the default search driver', function (): void {
    searchProviderSources();
    app()->register(SearchServiceProvider::class);

    expect(searchProviderResultTitles())->toBe(['Provider marker registry']);
});

test('provider returns the configured database search driver', function (): void {
    searchProviderSources();
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.driver', SearchDriver::Database->value);

    expect(searchProviderResultTitles())->toBe(['Provider marker database']);
});

test('provider returns the site discovery search driver', function (): void {
    searchProviderSources();
    app()->register(SearchServiceProvider::class);
    config()->set('capell-search.driver', SearchDriver::SiteDiscovery->value);

    expect(searchProviderResultTitles())->toBe(['Provider marker registry']);
});

test('provider prefers the settings driver over config', function (): void {
    $settings = new SearchSettings;
    $settings->driver = SearchDriver::Database;

    app()->instance(SearchSettings::class, $settings);
    searchProviderSources();
    app()->register(SearchServiceProvider::class);

    config()->set('capell-search.driver', SearchDriver::Scout->value);

    expect(searchProviderResultTitles())->toBe(['Provider marker database']);
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

test('provider registers search logs only through the installed package lifecycle', function (): void {
    InstalledRuntimeState::run(function (): void {
        $manifest = InstalledRuntimeParity::manifest('search');
        InstalledRuntimeTestCase::boot($manifest, false, sourceRoot: dirname(__DIR__, 3));
        expect(CapellCore::getModels())->not->toContain(SearchLog::class);

        InstalledRuntimeTestCase::boot($manifest, true, sourceRoot: dirname(__DIR__, 3));
        expect(CapellCore::getModels())->toContain(SearchLog::class);
    });
});

test('late installation does not expose search commands in an HTTP application', function (): void {
    $root = dirname(__DIR__, 5);
    $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
$app = new class(getcwd()) extends Illuminate\Foundation\Application {
    #[Override]
    public function runningInConsole(): bool { return false; }
};
$app->instance('config', new Illuminate\Config\Repository);
$app->instance('env', 'testing');
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$events = new Illuminate\Events\Dispatcher($app);
$app->instance('events', $events);
$kernel = new Illuminate\Foundation\Console\Kernel($app, $events);
$app->instance(Illuminate\Contracts\Console\Kernel::class, $kernel);
$app->boot();
$app->bootstrapWith([]);
$kernel->all();
$app->register(Capell\Search\Providers\ConsoleServiceProvider::class);
echo json_encode(array_keys($kernel->all()), JSON_THROW_ON_ERROR);
PHP], $root);
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $commands = capell_json_array($process->getOutput());
    $manifest = capell_json_file_array(dirname(__DIR__, 3) . '/capell.json');
    foreach (capell_test_array($manifest['commands']) as $name) {
        expect($commands)->not->toContain($name);
    }
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
