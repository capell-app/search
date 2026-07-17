<?php

declare(strict_types=1);

namespace Capell\Search\Filament\Widgets;

use Capell\Admin\Contracts\CapellFilamentWidgetContract;
use Capell\Admin\Filament\Concerns\GatedByRoleAndSettings;
use Capell\Search\Actions\BuildTopClickedResultsQueryAction;
use Capell\Search\Actions\BuildTopSearchesQueryAction;
use Capell\Search\Actions\BuildZeroResultSearchesQueryAction;
use Capell\Search\Data\SearchTermSummaryData;
use Capell\Search\Filament\Widgets\Concerns\BuildsSearchInsightsWindow;
use Filament\Widgets\Widget;
use Override;

final class SearchOverviewStatsFilamentWidget extends Widget implements CapellFilamentWidgetContract
{
    use BuildsSearchInsightsWindow;
    use GatedByRoleAndSettings;

    /** @var list<string> */
    protected static array $rolesConfigKeys = ['admin', 'super_admin'];

    protected static string $settingsKey = 'search_overview';

    protected string $view = 'capell-search::filament.widgets.search-overview-stats';

    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $window = $this->getInsightsWindow();
        $topSearches = BuildTopSearchesQueryAction::run($window, null);
        $zeroResultSearches = BuildZeroResultSearchesQueryAction::run($window, null);
        $topClickedResults = BuildTopClickedResultsQueryAction::run($window, 3);
        $totalSearches = $topSearches->sum(fn (SearchTermSummaryData $summary): int => $summary->searches);
        $zeroResultTotal = $zeroResultSearches->sum(fn (SearchTermSummaryData $summary): int => $summary->searches);
        $zeroResultRate = $totalSearches === 0 ? 0.0 : round(($zeroResultTotal / $totalSearches) * 100, 1);
        $clickedSearches = $topClickedResults->sum(
            static fn (array $result): int => $result['clicks'],
        );
        $clickThroughRate = $totalSearches === 0 ? 0.0 : round(($clickedSearches / $totalSearches) * 100, 1);

        return [
            'totalSearches' => $totalSearches,
            'uniqueQueries' => $topSearches->count(),
            'totalResults' => $topSearches->sum(fn (SearchTermSummaryData $summary): int => $summary->resultsCount),
            'zeroResultRate' => $zeroResultRate,
            'clickThroughRate' => $clickThroughRate,
            'topClickedResults' => $topClickedResults,
        ];
    }
}
