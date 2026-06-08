<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Models\SearchLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static ?SearchLog run(SearchLog|Request $request, string $query, ?string $url = null, ?string $token = null, ?string $type = null, ?int $position = null, ?string $surface = null)
 */
final class RecordSearchResultClickAction
{
    use AsAction;

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

        $log = $this->logFromToken($token, $windowMinutes, $normalizedQuery);

        if (! $log instanceof SearchLog) {
            $log = SearchLog::query()
                ->where('normalized_query', $normalizedQuery)
                ->where('searched_at', '>=', now()->subMinutes($windowMinutes))
                ->where('site_id', is_object($site) ? (int) data_get($site, 'id') : null)
                ->where('language_id', is_object($language) ? (int) data_get($language, 'id') : null)
                ->where('ip_hash', $this->hashVisitorValue($request->ip()))
                ->where('user_agent_hash', $this->hashVisitorValue($request->userAgent()))
                ->latest('searched_at')
                ->first();
        }

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
        ApplySearchResultEnhancementsAction::forgetClickCountsCache($log->site_id, $log->language_id);

        return $log;
    }

    private function logFromToken(?string $token, int $windowMinutes, string $normalizedQuery): ?SearchLog
    {
        $payload = $this->tokenPayload($token);

        if ($payload === null) {
            return null;
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);

        if ($issuedAt <= 0 || $issuedAt < now()->subMinutes($windowMinutes)->timestamp) {
            return null;
        }

        if ($payload['query'] !== $normalizedQuery) {
            return null;
        }

        $log = SearchLog::query()
            ->where('normalized_query', $payload['query'])
            ->where('searched_at', '>=', now()->subMinutes($windowMinutes))
            ->where('site_id', $payload['site_id'])
            ->where('language_id', $payload['language_id'])
            ->latest('searched_at')
            ->first();

        return $log instanceof SearchLog ? $log : null;
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

    /**
     * @return array{query: string, site_id: int|null, language_id: int|null, issued_at: int}|null
     */
    private function tokenPayload(?string $token): ?array
    {
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

        $query = $payload['query'] ?? null;
        $issuedAt = $payload['issued_at'] ?? null;

        if (! is_string($query) || $query === '' || ! is_int($issuedAt)) {
            return null;
        }

        return [
            'query' => $query,
            'site_id' => is_numeric($payload['site_id'] ?? null) ? (int) $payload['site_id'] : null,
            'language_id' => is_numeric($payload['language_id'] ?? null) ? (int) $payload['language_id'] : null,
            'issued_at' => $issuedAt,
        ];
    }
}
