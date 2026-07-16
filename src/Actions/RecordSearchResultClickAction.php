<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Models\SearchLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use JsonException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?SearchLog run(SearchLog|Request $request, string $query, ?string $url = null, ?string $token = null, ?string $type = null, ?int $position = null, ?string $surface = null)
 */
final class RecordSearchResultClickAction
{
    use AsFake;
    use AsObject;

    public function handle(
        SearchLog|Request $request,
        string $query,
        ?string $url = null,
        ?string $token = null,
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

        $log = $this->logFromToken(
            token: $token,
            windowMinutes: $windowMinutes,
            normalizedQuery: $normalizedQuery,
            siteId: is_object($site) ? (int) data_get($site, 'id') : null,
            languageId: is_object($language) ? (int) data_get($language, 'id') : null,
        );

        if (! $log instanceof SearchLog) {
            return null;
        }

        $resultPath = parse_url($url, PHP_URL_PATH);

        if (! is_string($resultPath) || $resultPath === '' || $resultPath !== $log->getRelation('tokenResultPath')) {
            return null;
        }

        return $this->recordClick($log, $resultPath);
    }

    private function recordClick(SearchLog $log, string $url): SearchLog
    {
        if ($log->clicked_result_url !== null) {
            return $log;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $log;
        }

        $recorded = SearchLog::query()
            ->whereKey($log->getKey())
            ->whereNull('clicked_result_url')
            ->update([
                'clicked_result_url' => Crypt::encryptString(mb_substr($path, 0, 255)),
                'clicked_result_hash' => HashSearchRetentionValueAction::run($path),
            ]);

        $log->unsetRelation('tokenResultPath');

        if ($recorded !== 1) {
            return $log->refresh();
        }

        $log->refresh();
        ApplySearchResultEnhancementsAction::forgetClickCountsCache($log->site_id, $log->language_id);

        return $log;
    }

    private function logFromToken(
        ?string $token,
        int $windowMinutes,
        string $normalizedQuery,
        ?int $siteId,
        ?int $languageId,
    ): ?SearchLog {
        if ($token === null || trim($token) === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $queryHash = $payload['query_hash'] ?? null;
        $resultPath = $payload['result_path'] ?? null;
        $issuedAt = $payload['issued_at'] ?? null;

        if (! is_string($queryHash) || $queryHash === '' || ! is_string($resultPath) || $resultPath === '' || ! is_int($issuedAt)) {
            return null;
        }

        if ($issuedAt <= 0 || $issuedAt < now()->subMinutes($windowMinutes)->timestamp) {
            return null;
        }

        if (! hash_equals($queryHash, HashSearchRetentionValueAction::run($normalizedQuery))) {
            return null;
        }

        $tokenSiteId = is_numeric($payload['site_id'] ?? null) ? (int) $payload['site_id'] : null;
        $tokenLanguageId = is_numeric($payload['language_id'] ?? null) ? (int) $payload['language_id'] : null;

        if ($tokenSiteId !== $siteId || $tokenLanguageId !== $languageId) {
            return null;
        }

        $log = SearchLog::query()
            ->where('normalized_query_hash', $queryHash)
            ->where('searched_at', '>=', now()->subMinutes($windowMinutes))
            ->where('site_id', $tokenSiteId)
            ->where('language_id', $tokenLanguageId)
            ->latest('searched_at')
            ->first();

        if (! $log instanceof SearchLog || $log->clicked_result_url !== null) {
            return null;
        }

        $log->setRelation('tokenResultPath', $resultPath);

        return $log;
    }
}
