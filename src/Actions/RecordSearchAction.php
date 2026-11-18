<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchVisitorIdentityData;
use Capell\Search\Models\SearchLog;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsJob;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?SearchLog run(SearchRequestData $data, int $resultsCount, ?SearchVisitorIdentityData $visitorIdentity = null)
 */
final class RecordSearchAction
{
    use AsFake;
    use AsJob;
    use AsObject;

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

        // Personal identifiers typed into the box (emails, phone or card-like numbers) are never retained.
        $normalizedQuery = RedactSearchQueryAction::run((string) NormalizeSearchQueryAction::run($data->query));
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

        $retainVisitorHashes = (bool) ResolveSearchSettingAction::run(
            'hash_visitor_data',
            'capell-search.hash_visitor_data',
            true,
        );

        // Unguarded: the reference column is added by a later migration, and Eloquent's guard check reads a cached column list.
        return SearchLog::unguarded(static fn (): SearchLog => SearchLog::query()->create([
            ...($data->reference !== null && SchemaFacade::hasColumn((new SearchLog)->getTable(), 'search_reference') ? ['search_reference' => $data->reference] : []),
            'site_id' => $data->siteId,
            'language_id' => $data->languageId,
            // Retain one canonical value rather than the visitor's raw spacing/casing.
            'query' => mb_substr($normalizedQuery, 0, 255),
            'normalized_query' => HashSearchRetentionValueAction::run($normalizedQuery),
            'normalized_query_hash' => HashSearchRetentionValueAction::run($normalizedQuery),
            'results_count' => $resultsCount,
            'ip_hash' => $retainVisitorHashes ? $visitorIdentity?->ipHash : null,
            'user_agent_hash' => $retainVisitorHashes ? $visitorIdentity?->userAgentHash : null,
            'searched_at' => now(),
        ]));
    }
}
