<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Support\Hashing\SearchLogHashSecret;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Re-keys the query and clicked-result hashes of search logs written under an earlier secret
 * (APP_KEY before search logs had their own, or a rotated CAPELL_SEARCH_LOG_HASH_SECRET).
 *
 * HMAC cannot be re-keyed, but each row keeps its query and clicked result encrypted, so the hash
 * is recomputed from the decrypted value. Rows already keyed by the current secret are skipped,
 * so running it again changes nothing. Rows still awaiting legacy encryption are left to
 * EncryptLegacySearchLogPiiAction, and a row whose values no longer decrypt is left untouched and counted.
 *
 * @method static array{rehashed: int, undecryptable: int} run(int $chunkSize = 1000)
 */
final class RehashSearchLogsAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array{rehashed: int, undecryptable: int}
     */
    public function handle(int $chunkSize = 1000): array
    {
        $secret = SearchLogHashSecret::current();
        $keyId = SearchLogHashSecret::keyId($secret);
        $table = EncryptLegacySearchLogPiiAction::table();
        $rehashed = 0;
        $undecryptable = 0;

        // Page by id: each re-keyed row leaves the filter, so offset pages would skip rows.
        $this->staleRows($keyId)->select(['id', 'query', 'clicked_result_url'])->eachById(
            static function (object $log) use (&$rehashed, &$undecryptable, $secret, $keyId, $table): void {
                try {
                    $query = Crypt::decryptString((string) $log->query);
                    $clickedPath = is_string($log->clicked_result_url) && $log->clicked_result_url !== ''
                        ? Crypt::decryptString($log->clicked_result_url)
                        : null;
                } catch (DecryptException) {
                    $undecryptable++;

                    return;
                }

                // The stored query is the display value (a legacy row keeps its original case and spacing),
                // so hash the same normalised, redacted form RecordSearchAction hashes, or re-keyed rows
                // would stop grouping with the same search typed after the rotation.
                $queryHash = HashSearchRetentionValueAction::run(RedactSearchQueryAction::run(NormalizeSearchQueryAction::run($query)), $secret);

                DB::table($table)->where('id', $log->id)->update([
                    'normalized_query' => $queryHash,
                    'normalized_query_hash' => $queryHash,
                    'clicked_result_hash' => $clickedPath === null ? null : HashSearchRetentionValueAction::run($clickedPath, $secret),
                    'hash_key_id' => $keyId,
                ]);
                $rehashed++;
            },
            $chunkSize,
        );

        return ['rehashed' => $rehashed, 'undecryptable' => $undecryptable];
    }

    /**
     * Rows whose hashes were keyed by a secret other than the current one. False while the secret
     * is missing or the table predates the key id column.
     */
    public function hasStaleRows(): bool
    {
        if (! SearchLogHashSecret::configured() || ! $this->hasKeyIdColumn()) {
            return false;
        }

        return $this->staleRows(SearchLogHashSecret::currentKeyId())->exists();
    }

    public function staleRowCount(): int
    {
        if (! SearchLogHashSecret::configured() || ! $this->hasKeyIdColumn()) {
            return 0;
        }

        return $this->staleRows(SearchLogHashSecret::currentKeyId())->count();
    }

    private function hasKeyIdColumn(): bool
    {
        $table = EncryptLegacySearchLogPiiAction::table();

        return Schema::hasTable($table) && Schema::hasColumn($table, 'hash_key_id');
    }

    private function staleRows(string $keyId): Builder
    {
        return DB::table(EncryptLegacySearchLogPiiAction::table())
            // Converted rows only: a legacy row stores a differing (or no) normalized_query_hash.
            ->whereNotNull('normalized_query_hash')
            ->whereColumn('normalized_query', 'normalized_query_hash')
            ->where(static function (Builder $query) use ($keyId): void {
                $query->whereNull('hash_key_id')->orWhere('hash_key_id', '!=', $keyId);
            });
    }
}
