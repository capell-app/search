<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Models\SearchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Lorisleiva\Actions\Concerns\AsAction;

final class RecordSearchResultClickAction
{
    use AsAction;

    public function handle(
        SearchLog|Request $request,
        string $query,
        ?string $url = null,
        ?string $type = null,
        ?int $position = null,
        ?string $surface = null,
    ): ?SearchLog {
        if ($request instanceof SearchLog) {
            return $this->recordClick($request, $query);
        }

        if ($url === null || trim($url) === '') {
            return null;
        }

        $normalizedQuery = NormalizeSearchQueryAction::run($query);

        if ($normalizedQuery === '' || ! SchemaFacade::hasTable((new SearchLog)->getTable())) {
            return null;
        }

        $site = $request->attributes->get('site');
        $language = $request->attributes->get('language');
        $windowMinutes = max(1, (int) config('capell-search.click_tracking.match_window_minutes', 30));

        $log = SearchLog::query()
            ->where('normalized_query', $normalizedQuery)
            ->where('searched_at', '>=', now()->subMinutes($windowMinutes))
            ->where('site_id', is_object($site) ? (int) data_get($site, 'id') : null)
            ->where('language_id', is_object($language) ? (int) data_get($language, 'id') : null)
            ->where('ip_hash', $this->hashVisitorValue($request->ip()))
            ->where('user_agent_hash', $this->hashVisitorValue($request->userAgent()))
            ->latest('searched_at')
            ->first();

        if (! $log instanceof SearchLog) {
            return null;
        }

        return $this->recordClick($log, $url);
    }

    private function recordClick(SearchLog $log, string $url): SearchLog
    {
        $log->forceFill([
            'clicked_result_url' => $url,
        ]);

        $log->save();

        return $log;
    }

    private function hashVisitorValue(?string $value): ?string
    {
        if (! (bool) ResolveSearchSettingAction::run(
            'hash_visitor_data',
            'capell-search.hash_visitor_data',
            true,
        )) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value . '|' . config('app.key'));
    }
}
