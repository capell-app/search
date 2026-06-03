<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchInsightsWindowData;
use Capell\Search\Models\SearchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class BuildTopClickedResultsQueryAction
{
    use AsAction;

    /**
     * @return Collection<int, array{url: string, clicks: int}>
     */
    public function handle(SearchInsightsWindowData $window, ?int $limit = 5): Collection
    {
        if ($limit !== null && $limit <= 0) {
            return collect();
        }

        $query = SearchLog::query()
            ->select([
                'clicked_result_url',
                DB::raw('COUNT(*) as clicks'),
            ])
            ->whereNotNull('clicked_result_url')
            ->whereBetween('searched_at', [$window->start, $window->end])
            ->when($window->siteId !== null, fn (Builder $query): Builder => $query->where('site_id', $window->siteId))
            ->groupBy('clicked_result_url')
            ->orderByDesc('clicks');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return DB::query()
            ->fromSub($query, 'clicked_results')
            ->get()
            ->map(static fn (object $result): array => [
                'url' => (string) $result->clicked_result_url,
                'clicks' => (int) $result->clicks,
            ])
            ->values();
    }
}
