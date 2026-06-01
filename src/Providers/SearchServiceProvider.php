<?php

declare(strict_types=1);

namespace Capell\Search\Providers;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\Settings\SettingsGroupMetadata;
use Capell\Core\Support\Settings\SettingsSchemaRegistry;
use Capell\Search\Actions\ResolveSearchSettingAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Drivers\ScoutSearch;
use Capell\Search\Drivers\SiteDiscoverySearch;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Capell\Search\Models\SearchLog;
use Capell\Search\Settings\SearchSettings;
use Capell\Search\Support\RenderHooks\RegisterHeaderSearchHook;
use Capell\Search\Support\SiteDiscovery\SearchGeneratedOutputCoverageSource;
use Capell\SiteDiscovery\Contracts\GeneratedOutputCoverageSource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Spatie\LaravelPackageTools\Package;

final class SearchServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-search';

    public static string $packageName = 'capell-app/search';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('capell-search')
            ->hasTranslations();

        if (is_dir(__DIR__ . '/../../resources/views')) {
            $package->hasViews();
        }

        if (file_exists(__DIR__ . '/../../database/migrations/2026_05_10_190868_01_create_search_logs_table.php')) {
            $package->hasMigrations([
                '2026_05_10_190868_01_create_search_logs_table',
                '2026_05_21_000002_add_fulltext_index_to_search_database_table',
            ]);
        }

        if (file_exists(__DIR__ . '/../../routes/web.php')) {
            $package->hasRoute('web');
        }
    }

    public function registeringPackage(): void
    {
        $this->app->register(AdminServiceProvider::class);
    }

    public function packageRegistered(): void
    {
        $this->registerSearchBinding();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this
                ->registerModels()
                ->registerSettings()
                ->registerGeneratedOutputCoverage()
                ->registerProtectedTables();
        });
    }

    public function packageBooted(): void
    {
        if (! $this->isPackageInstalled()) {
            return;
        }

        if (! class_exists(RegisterHeaderSearchHook::class)) {
            return;
        }

        $this->app->make(RegisterHeaderSearchHook::class)->register();
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    private function registerSearchBinding(): self
    {
        if (! interface_exists(Search::class)) {
            return $this;
        }

        if (! class_exists(DatabaseSearch::class)) {
            return $this;
        }

        $this->app->bind(Search::class, function (Application $app): Search {
            $driver = $this->resolveDriver();

            if ($driver === 'scout' && class_exists(ScoutSearch::class)) {
                return new ScoutSearch(
                    modelClass: config('capell-search.scout.model'),
                    urlColumn: config('capell-search.scout.url_column', 'slug'),
                    typeColumn: config('capell-search.scout.type_column', 'type'),
                    excerptLength: config('capell-search.excerpt_length', 200),
                );
            }

            if ($driver === SearchDriver::SiteDiscovery->value && class_exists(SiteDiscoverySearch::class)) {
                return new SiteDiscoverySearch(
                    excerptLength: config('capell-search.excerpt_length', 200),
                );
            }

            return new DatabaseSearch(
                db: $app->make(ConnectionResolverInterface::class)->connection(),
                table: config('capell-search.database.table', 'pages'),
                columns: config('capell-search.database.columns', ['title', 'excerpt', 'body']),
                urlColumn: config('capell-search.database.url_column', 'slug'),
                typeColumn: config('capell-search.database.type_column', 'type'),
                titleColumn: config('capell-search.database.title_column', 'title'),
                excerptColumn: config('capell-search.database.excerpt_column', 'excerpt'),
                bodyColumn: config('capell-search.database.body_column', 'body'),
                siteColumn: config('capell-search.database.site_column', 'site_id'),
                languageColumn: config('capell-search.database.language_column', 'language_id'),
                statusColumn: config('capell-search.database.status_column', 'status'),
                publishedStatus: config('capell-search.database.published_status', 'published'),
            );
        });

        return $this;
    }

    private function resolveDriver(): string
    {
        $configDriver = ResolveSearchSettingAction::run(
            'driver',
            'capell-search.driver',
            SearchDriver::Database->value,
        );

        if ($configDriver instanceof SearchDriver) {
            return $configDriver->value;
        }

        if (is_string($configDriver) && $configDriver !== '') {
            return $configDriver;
        }

        return SearchDriver::Database->value;
    }

    private function registerModels(): self
    {
        if (! class_exists(SearchLog::class)) {
            return $this;
        }

        CapellCore::registerModels([SearchLog::class]);

        return $this;
    }

    private function registerSettings(): self
    {
        if (! class_exists(SettingsSchemaRegistry::class)) {
            return $this;
        }

        if (! class_exists(SearchSettings::class) || ! class_exists(SearchSettingsSchema::class)) {
            return $this;
        }

        /** @var SettingsSchemaRegistry $registry */
        $registry = $this->app->make(SettingsSchemaRegistry::class);

        $registry->registerSettingsClass('search', SearchSettings::class);
        $registry->registerMetadata(new SettingsGroupMetadata(
            group: 'search',
            label: 'capell-search::settings.title',
            icon: Heroicon::OutlinedMagnifyingGlass,
            navigationGroup: 'capell-admin::navigation.group_system',
            navigationSort: 95,
            packageName: self::$packageName,
        ));
        $registry->register('search', SearchSettingsSchema::class);

        return $this;
    }

    private function registerProtectedTables(): self
    {
        CapellCore::registerProtectedTable(fn (): string => config('capell-search.logs.table_name', 'search_logs'));

        return $this;
    }

    private function registerGeneratedOutputCoverage(): self
    {
        if (! interface_exists(GeneratedOutputCoverageSource::class)) {
            return $this;
        }

        $this->app->singleton(SearchGeneratedOutputCoverageSource::class);
        $this->app->tag([SearchGeneratedOutputCoverageSource::class], GeneratedOutputCoverageSource::TAG);

        return $this;
    }
}
