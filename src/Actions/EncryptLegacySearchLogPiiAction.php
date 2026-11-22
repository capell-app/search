<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Core\Support\TableName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Encrypts the query and clicked result of search logs written before logs stored
 * them encrypted, and replaces the normalised query with its keyed hash. A converted
 * row stores the same hash in normalized_query and normalized_query_hash, so running
 * this again leaves it untouched.
 *
 * @method static int run(int $chunkSize = 1000)
 */
final class EncryptLegacySearchLogPiiAction
{
    use AsFake;
    use AsObject;

    public static function table(): string
    {
        return TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));
    }

    public function handle(int $chunkSize = 1000): int
    {
        $converted = 0;

        // Page by id, not offset: each converted row leaves the legacy filter, so offset pages would skip rows.

        $this->legacyRows()->select(['id', 'query', 'normalized_query', 'clicked_result_url'])->eachById(
            function (object $log) use (&$converted): void {
                $normalized = is_string($log->normalized_query) ? $log->normalized_query : '';
                $query = is_string($log->query) ? $log->query : $normalized;
                $clickedPath = is_string($log->clicked_result_url) && $log->clicked_result_url !== '' ? $log->clicked_result_url : null;
                $queryHash = HashSearchRetentionValueAction::run($normalized);

                DB::table(self::table())->where('id', $log->id)->update([
                    'query' => Crypt::encryptString($query),
                    'normalized_query' => $queryHash,
                    'normalized_query_hash' => $queryHash,
                    'clicked_result_url' => $clickedPath === null ? null : Crypt::encryptString($clickedPath),
                    'clicked_result_hash' => $clickedPath === null ? null : HashSearchRetentionValueAction::run($clickedPath),
                ]);
                $converted++;
            },
            $chunkSize,
        );

        return $converted;
    }

    public function hasLegacyRows(): bool
    {
        return $this->legacyRows()->exists();
    }

    private function legacyRows(): Builder
    {
        return DB::table(self::table())->where(static function (Builder $query): void {
            $query->whereNull('normalized_query_hash')
                ->orWhereNull('normalized_query')
                ->orWhereColumn('normalized_query', '!=', 'normalized_query_hash');
        });
    }
}
