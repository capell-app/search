<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->searchLogsTable();
        if (! Schema::hasColumn($tableName, 'normalized_query_hash') || ! Schema::hasColumn($tableName, 'clicked_result_hash')) {
            Schema::table($tableName, static function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'normalized_query_hash')) {
                    $table->string('normalized_query_hash', 64)->nullable()->index();
                }

                if (! Schema::hasColumn($tableName, 'clicked_result_hash')) {
                    $table->string('clicked_result_hash', 64)->nullable()->index();
                }
            });
        }

        $secret = config('capell-search.logs.hash_secret');
        $secret = is_string($secret) && $secret !== '' ? $secret : config('app.key');
        throw_unless(is_string($secret) && $secret !== '', RuntimeException::class, 'Search retention hash secret is unavailable.');

        DB::table($tableName)->select(['id', 'query', 'normalized_query', 'clicked_result_url'])->orderBy('id')->each(
            static function (object $log) use ($secret, $tableName): void {
                $normalized = is_string($log->normalized_query) ? $log->normalized_query : '';
                $query = is_string($log->query) ? $log->query : $normalized;
                $clickedPath = is_string($log->clicked_result_url) && $log->clicked_result_url !== '' ? $log->clicked_result_url : null;
                $queryHash = hash_hmac('sha256', $normalized, $secret);

                DB::table($tableName)->where('id', $log->id)->update([
                    'query' => Crypt::encryptString($query),
                    'normalized_query' => $queryHash,
                    'normalized_query_hash' => $queryHash,
                    'clicked_result_url' => $clickedPath === null ? null : Crypt::encryptString($clickedPath),
                    'clicked_result_hash' => $clickedPath === null ? null : hash_hmac('sha256', $clickedPath, $secret),
                ]);
            },
        );
    }

    public function down(): void
    {
        $tableName = $this->searchLogsTable();
        DB::table($tableName)->select(['id', 'query', 'clicked_result_url'])->orderBy('id')->each(
            static function (object $log) use ($tableName): void {
                DB::table($tableName)->where('id', $log->id)->update([
                    'query' => is_string($log->query) ? Crypt::decryptString($log->query) : '',
                    'clicked_result_url' => is_string($log->clicked_result_url) ? Crypt::decryptString($log->clicked_result_url) : null,
                ]);
            },
        );
        Schema::table($tableName, static function (Blueprint $table): void {
            $table->dropColumn(['normalized_query_hash', 'clicked_result_hash']);
        });
    }

    private function searchLogsTable(): string
    {
        $configuredTable = config('capell-search.logs.table_name', 'search_logs');

        return is_string($configuredTable) ? $configuredTable : 'search_logs';
    }
};
