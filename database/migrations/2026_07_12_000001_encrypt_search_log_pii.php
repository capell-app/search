<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
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

        // Existing rows are encrypted by the EncryptSearchLogPiiStep upgrade step, which runs straight after
        // this migration under Core's upgrade lock and can be re-run safely.
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
        return TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));
    }
};
