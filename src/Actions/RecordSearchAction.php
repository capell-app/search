<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchVisitorIdentityData;
use Capell\Search\Models\SearchLog;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static ?SearchLog run(SearchRequestData $data, int $resultsCount, ?SearchVisitorIdentityData $visitorIdentity = null)
 */
final class RecordSearchAction
{
    use AsAction;

    public function handle(
        SearchRequestData $data,
        int $resultsCount,
        ?SearchVisitorIdentityData $visitorIdentity = null,
    ): ?SearchLog {
        if (! (bool) ResolveSearchSettingAction::run(
            'record_search_logs',
            'capell-search.record_search_logs',
            true,
        )) {
            return null;
        }

        $normalizedQuery = NormalizeSearchQueryAction::run($data->query);
        $minimumLength = (int) ResolveSearchSettingAction::run(
            'minimum_query_length',
            'capell-search.minimum_query_length',
            2,
        );

        if ($normalizedQuery === '' || mb_strlen((string) $normalizedQuery) < $minimumLength) {
            return null;
        }

        if (! SchemaFacade::hasTable((new SearchLog)->getTable())) {
            return null;
        }

        return SearchLog::query()->create([
            'site_id' => $data->siteId,
            'language_id' => $data->languageId,
            'query' => $data->query,
            'normalized_query' => $normalizedQuery,
            'results_count' => $resultsCount,
            'ip_hash' => $visitorIdentity?->ipHash,
            'user_agent_hash' => $visitorIdentity?->userAgentHash,
            'searched_at' => now(),
        ]);
    }
}
