<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Capell\Core\Contracts\Extensions\RegistersExtensionWidget;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;
use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\BuildTopClickedResultsQueryAction;
use Capell\Search\Actions\BuildTopSearchesQueryAction;
use Capell\Search\Actions\BuildTrendingSearchesQueryAction;
use Capell\Search\Actions\BuildZeroResultSearchesQueryAction;
use Capell\Search\Actions\FlushScoutSearchSourcesAction;
use Capell\Search\Actions\IndexScoutSearchSourcesAction;
use Capell\Search\Actions\ResolveExpandedSearchQueriesAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Actions\RunSearchAction;
use Capell\Search\Filament\Widgets\TopSearchesWidget;
use Capell\Search\Filament\Widgets\TrendingSearchesWidget;
use Capell\Search\Filament\Widgets\ZeroResultSearchesWidget;
use Capell\Search\Manifest\SearchFrontendRouteContribution;
use Capell\Search\Manifest\SearchLogModelContribution;
use Capell\Search\Manifest\SearchLogPurgeScheduleContribution;
use Capell\Search\Manifest\SearchOverviewStatsContribution;
use Capell\Search\Manifest\TopSearchesWidgetContribution;
use Capell\Search\Manifest\TrendingSearchesWidgetContribution;
use Capell\Search\Manifest\ZeroResultSearchesWidgetContribution;
use Capell\Search\Models\SearchLog;
use Capell\Search\Settings\SearchSettings;
use Pest\Expectation;

it('declares implemented search gap features contributions and actions', function (): void {
    $manifest = capell_json_file_array(__DIR__ . '/../../capell.json');
    $screenshots = data_get($manifest, 'marketplace.screenshots', []);

    throw_unless(is_array($screenshots), RuntimeException::class, 'Search screenshots must be an array.');

    $screenshotPaths = array_map(
        static function (mixed $screenshot): string {
            throw_unless(is_array($screenshot), RuntimeException::class, 'Search screenshot entries must be arrays.');

            $path = $screenshot['path'] ?? null;

            throw_unless(is_string($path), RuntimeException::class, 'Search screenshot paths must be strings.');

            return __DIR__ . '/../../' . $path;
        },
        $screenshots,
    );

    expect(data_get($manifest, 'description'))->toContain('synonyms')
        ->and(data_get($manifest, 'description'))->toContain('Site Discovery URL registry')
        ->and(data_get($manifest, 'marketplace.summary'))->toContain('Production-grade site search')
        ->and($screenshotPaths)->each(fn (Expectation $path): Expectation => $path->toBeFile())
        ->and(data_get($manifest, 'dependencies.supports'))->toContain('capell-app/site-discovery')
        ->and(data_get($manifest, 'commands.flush'))->toBe('search:flush')
        ->and(data_get($manifest, 'commands.index'))->toBe('search:index')
        ->and(data_get($manifest, 'commands.purge'))->toBe('search:purge')
        ->and(data_get($manifest, 'settings'))->toContain(SearchSettings::class)
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'route',
            'class' => SearchFrontendRouteContribution::class,
            'routes' => ['capell-frontend.search'],
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'model',
            'class' => SearchLogModelContribution::class,
            'modelClass' => SearchLog::class,
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'dashboard-widget',
            'class' => TopSearchesWidgetContribution::class,
            'widgetClass' => TopSearchesWidget::class,
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'dashboard-widget',
            'class' => TrendingSearchesWidgetContribution::class,
            'widgetClass' => TrendingSearchesWidget::class,
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'dashboard-widget',
            'class' => ZeroResultSearchesWidgetContribution::class,
            'widgetClass' => ZeroResultSearchesWidget::class,
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'overview-stat',
            'class' => SearchOverviewStatsContribution::class,
            'keys' => [
                'search_overview',
                'search_overview.unique_queries',
                'search_overview.zero_result_rate',
            ],
        ])
        ->and($manifest['contributes'])->toContain([
            'type' => 'scheduled-job',
            'class' => SearchLogPurgeScheduleContribution::class,
            'command' => 'search:purge',
            'frequency' => 'monthly',
        ])
        ->and(data_get($manifest, 'actions'))->toHaveKey('applySearchResultEnhancements', ApplySearchResultEnhancementsAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildTopClickedResultsQuery', BuildTopClickedResultsQueryAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildTopSearchesQuery', BuildTopSearchesQueryAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildTrendingSearchesQuery', BuildTrendingSearchesQueryAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('buildZeroResultSearchesQuery', BuildZeroResultSearchesQueryAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('flushScoutSearchSources', FlushScoutSearchSourcesAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('indexScoutSearchSources', IndexScoutSearchSourcesAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('resolveExpandedSearchQueries', ResolveExpandedSearchQueriesAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('resolvePromotedSearchResults', ResolvePromotedSearchResultsAction::class)
        ->and(data_get($manifest, 'actions'))->toHaveKey('runSearch', RunSearchAction::class)
        ->and(data_get($manifest, 'capabilities'))->toContain(
            'search-synonyms',
            'search-promoted-results',
            'search-typo-corrections',
            'search-source-weighting',
            'search-zero-result-reporting',
            'search-site-discovery-indexing',
        )
        ->and(class_implements(SearchFrontendRouteContribution::class))->toContain(RegistersExtensionRoute::class)
        ->and(class_implements(TopSearchesWidgetContribution::class))->toContain(RegistersExtensionWidget::class)
        ->and(class_implements(TrendingSearchesWidgetContribution::class))->toContain(RegistersExtensionWidget::class)
        ->and(class_implements(ZeroResultSearchesWidgetContribution::class))->toContain(RegistersExtensionWidget::class)
        ->and(class_implements(SearchOverviewStatsContribution::class))->toContain(RegistersExtensionWidget::class)
        ->and(class_implements(SearchLogPurgeScheduleContribution::class))->toContain(RunsScheduledExtensionJob::class)
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->not->toContain(
            'dashboard-widget',
            'model',
            'overview-stat',
            'route',
            'scheduled-job',
        );
});
