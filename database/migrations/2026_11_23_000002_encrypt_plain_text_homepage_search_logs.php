<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Capell\Search\Actions\HashSearchRetentionValueAction;
use Capell\Search\Actions\RedactSearchQueryAction;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage package searches were written straight into search_logs, around the SearchLog model's
 * encrypted cast, so their query and normalised query sit in plain text. Search's own upgrade step
 * that encrypts legacy rows has already run on existing installs and will not run again, so this
 * converts those rows the same way: personal identifiers redacted, the query encrypted, the
 * normalised query replaced by its keyed hash. Their visitor hashes were keyed by APP_KEY from the
 * raw IP address and user agent, never rotated, and cannot be re-keyed, so they are cleared.
 *
 * Only rows with no normalised-query hash whose query does not already decrypt are touched, so it
 * is safe to run again. down() leaves the rows encrypted: restoring plain text would reintroduce
 * the exposure, and the encrypted rows read correctly through the SearchLog model.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'normalized_query_hash')) {
            return;
        }

        DB::table($table)
            ->whereNull('normalized_query_hash')
            ->select(['id', 'query', 'normalized_query'])
            ->eachById(static function (object $log) use ($table): void {
                $query = is_string($log->query) ? $log->query : '';

                if ($query !== '' && self::isEncrypted($query)) {
                    return;
                }

                $redactedQuery = RedactSearchQueryAction::run($query);
                $normalizedQuery = RedactSearchQueryAction::run(is_string($log->normalized_query) && $log->normalized_query !== ''
                    ? $log->normalized_query
                    : mb_strtolower($query));
                $queryHash = HashSearchRetentionValueAction::run($normalizedQuery);

                DB::table($table)->where('id', $log->id)->whereNull('normalized_query_hash')->update([
                    'query' => Crypt::encryptString($redactedQuery),
                    'normalized_query' => $queryHash,
                    'normalized_query_hash' => $queryHash,
                    'ip_hash' => null,
                    'user_agent_hash' => null,
                ]);
            }, 500);
    }

    public function down(): void
    {
        // Intentionally irreversible: see the class comment.
    }

    private static function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
