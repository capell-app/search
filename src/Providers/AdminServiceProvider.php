<?php

declare(strict_types=1);

namespace Capell\Search\Providers;

use Capell\Admin\Contracts\DashboardSettingsContributor;
use Capell\Admin\Data\Extensions\ExtensionManagementSurfaceData;
use Capell\Admin\Enums\DashboardEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Search\Actions\BuildTopSearchesQueryAction;
use Capell\Search\Actions\BuildZeroResultSearchesQueryAction;
use Capell\Search\Console\Commands\FlushSearchCommand;
use Capell\Search\Console\Commands\IndexSearchCommand;
use Capell\Search\Console\Commands\PurgeSearchLogsCommand;
use Capell\Search\Data\SearchInsightsWindowData;
use Capell\Search\Filament\Settings\Contributors\SearchDashboardSettingsContributor;
use Capell\Search\Filament\Widgets\TopSearchesWidget;
use Capell\Search\Filament\Widgets\TrendingSearchesWidget;
use Capell\Search\Filament\Widgets\ZeroResultSearchesWidget;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Override;
use Spatie\Permission\PermissionRegistrar;

final class AdminServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->isPackageInstalled()) {
            return;
        }

        $this->registerDashboardSettingsContributor()
            ->registerCommands()
            ->registerExtensionPages()
            ->registerOverviewStats()
            ->registerDashboardWidgets()
            ->registerSchedule();
    }

    private function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(SearchServiceProvider::$packageName);
    }

    private function registerDashboardSettingsContributor(): self
    {
        if (! class_exists(SearchDashboardSettingsContributor::class)) {
            return $this;
        }

        $this->app->tag([SearchDashboardSettingsContributor::class], DashboardSettingsContributor::TAG);

        return $this;
    }

    private function registerExtensionPages(): self
    {
        CapellAdmin::registerExtensionManagementSurface(ExtensionManagementSurfaceData::settings(
            packageName: SearchServiceProvider::$packageName,
            label: 'capell-search::settings.title',
            settingsGroup: 'search',
            icon: 'heroicon-o-magnifying-glass',
        ));

        return $this;
    }

    private function registerCommands(): self
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        if (! class_exists(PurgeSearchLogsCommand::class)) {
            return $this;
        }

        $commands = [
            PurgeSearchLogsCommand::class,
        ];

        if (class_exists(IndexSearchCommand::class)) {
            $commands[] = IndexSearchCommand::class;
        }

        if (class_exists(FlushSearchCommand::class)) {
            $commands[] = FlushSearchCommand::class;
        }

        $this->commands($commands);

        return $this;
    }

    private function registerDashboardWidgets(): self
    {
        $widgetClasses = [
            TopSearchesWidget::class,
            TrendingSearchesWidget::class,
            ZeroResultSearchesWidget::class,
        ];

        foreach ($widgetClasses as $widgetClass) {
            if (! class_exists($widgetClass)) {
                continue;
            }

            CapellAdmin::registerDashboardWidget($widgetClass, DashboardEnum::Main);
        }

        return $this;
    }

    private function registerOverviewStats(): self
    {
        CapellAdmin::registerOverviewStat(
            key: 'search_overview',
            label: fn (): string => __('capell-search::dashboard.searches'),
            value: fn (): int => $this->searchOverview()['totalSearches'],
            group: fn (): string => __('capell-search::dashboard.group'),
            sort: 110,
            settingsLabel: fn (): string => __('capell-search::dashboard.search_overview'),
        );

        CapellAdmin::registerOverviewStat(
            key: 'search_overview.unique_queries',
            label: fn (): string => __('capell-search::dashboard.query'),
            value: fn (): int => $this->searchOverview()['uniqueQueries'],
            group: fn (): string => __('capell-search::dashboard.group'),
            sort: 111,
            settingsKey: 'search_overview',
            settingsLabel: fn (): string => __('capell-search::dashboard.search_overview'),
        );

        CapellAdmin::registerOverviewStat(
            key: 'search_overview.zero_result_rate',
            label: fn (): string => __('capell-search::dashboard.zero_result_rate'),
            value: fn (): string => number_format($this->searchOverview()['zeroResultRate'], 1) . '%',
            group: fn (): string => __('capell-search::dashboard.group'),
            color: 'warning',
            sort: 112,
            settingsKey: 'search_overview',
            settingsLabel: fn (): string => __('capell-search::dashboard.search_overview'),
        );

        return $this;
    }

    /**
     * @return array{totalSearches: int, uniqueQueries: int, zeroResultRate: float}
     */
    private function searchOverview(): array
    {
        static $overview = [];

        $siteId = $this->currentDashboardSiteId();
        $cacheKey = $siteId === null ? 'global' : 'site-' . $siteId;

        if (isset($overview[$cacheKey])) {
            return $overview[$cacheKey];
        }

        $days = config('capell-search.dashboard.default_days', 30);
        $fallbackDays = is_int($days) ? max(1, $days) : 30;
        $window = new SearchInsightsWindowData(
            start: CarbonImmutable::now()->subDays($fallbackDays)->startOfDay(),
            end: CarbonImmutable::now()->endOfDay(),
            siteId: $siteId,
        );
        $topSearches = BuildTopSearchesQueryAction::run($window, null);
        $zeroResultSearches = BuildZeroResultSearchesQueryAction::run($window, null);
        $totalSearches = (int) $topSearches->sum('searches');
        $zeroResultTotal = (int) $zeroResultSearches->sum('searches');

        $overview[$cacheKey] = [
            'totalSearches' => $totalSearches,
            'uniqueQueries' => $topSearches->count(),
            'zeroResultRate' => $totalSearches === 0 ? 0.0 : round(($zeroResultTotal / $totalSearches) * 100, 1),
        ];

        return $overview[$cacheKey];
    }

    private function currentDashboardSiteId(): ?int
    {
        $siteId = resolve(PermissionRegistrar::class)->getPermissionsTeamId();

        return is_numeric($siteId) ? (int) $siteId : null;
    }

    private function registerSchedule(): self
    {
        if (! class_exists(PurgeSearchLogsCommand::class)) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('search:purge')->monthly();
        });

        return $this;
    }
}
