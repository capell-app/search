<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Capell\Core\Contracts\Extensions\RegistersExtensionWidget;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;
use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\BuildTopSearchesQueryAction;
use Capell\Search\Actions\BuildTrendingSearchesQueryAction;
use Capell\Search\Actions\BuildZeroResultSearchesQueryAction;
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
    $manifest = json_decode(
        (string) file_get_contents(__DIR__ . '/../../capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
    $screenshotPaths = array_map(
        static fn (array $screenshot): string => __DIR__ . '/../../' . $screenshot['path'],
        $manifest['marketplace']['screenshots'],
    );

    expect($manifest['description'])->toContain('synonyms')
        ->and($manifest['description'])->toContain('Site Discovery URL registry')
        ->and($manifest['marketplace']['summary'])->toContain('Production-grade site search')
        ->and($screenshotPaths)->each(fn (Expectation $path): Expectation => $path->toBeFile())
        ->and($manifest['dependencies']['supports'])->toContain('capell-app/site-discovery')
        ->and($manifest['commands']['purge'])->toBe('search:purge')
        ->and($manifest['settings'])->toContain(SearchSettings::class)
        ->and($manifest['contributes'])->toContain([
            'type' => 'route',
            'class' => SearchFrontendRouteContribution::class,
            'routes' => ['capell-frontend.search'],
        ])
        ->and($manifest['contributes'])->toContain([
            'type' => 'model',
            'class' => SearchLogModelContribution::class,
            'modelClass' => SearchLog::class,
        ])
        ->and($manifest['contributes'])->toContain([
            'type' => 'dashboard-widget',
            'class' => TopSearchesWidgetContribution::class,
            'widgetClass' => TopSearchesWidget::class,
        ])
        ->and($manifest['contributes'])->toContain([
            'type' => 'dashboard-widget',
            'class' => TrendingSearchesWidgetContribution::class,
            'widgetClass' => TrendingSearchesWidget::class,
        ])
        ->and($manifest['contributes'])->toContain([
            'type' => 'dashboard-widget',
            'class' => ZeroResultSearchesWidgetContribution::class,
            'widgetClass' => ZeroResultSearchesWidget::class,
        ])
        ->and($manifest['contributes'])->toContain([
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
        ->and($manifest['actions'])->toHaveKey('applySearchResultEnhancements', ApplySearchResultEnhancementsAction::class)
        ->and($manifest['actions'])->toHaveKey('buildTopSearchesQuery', BuildTopSearchesQueryAction::class)
        ->and($manifest['actions'])->toHaveKey('buildTrendingSearchesQuery', BuildTrendingSearchesQueryAction::class)
        ->and($manifest['actions'])->toHaveKey('buildZeroResultSearchesQuery', BuildZeroResultSearchesQueryAction::class)
        ->and($manifest['actions'])->toHaveKey('resolveExpandedSearchQueries', ResolveExpandedSearchQueriesAction::class)
        ->and($manifest['actions'])->toHaveKey('resolvePromotedSearchResults', ResolvePromotedSearchResultsAction::class)
        ->and($manifest['actions'])->toHaveKey('runSearch', RunSearchAction::class)
        ->and($manifest['capabilities'])->toContain(
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
        ->and($manifest['contributionTraceability']['deferredContributions'])->not->toContain(
            'dashboard-widget',
            'model',
            'overview-stat',
            'route',
            'scheduled-job',
        );
});
