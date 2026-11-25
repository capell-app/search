<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->searchLogsTable();

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'hash_key_id')) {
            return;
        }

        // Identifies the secret that keyed each row's hashes (never the secret), so rows keyed by an
        // earlier secret, including APP_KEY before search logs had their own, can be re-keyed.
        Schema::table($tableName, static function (Blueprint $table): void {
            $table->string('hash_key_id', 16)->nullable()->index();
        });
    }

    public function down(): void
    {
        $tableName = $this->searchLogsTable();

        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'hash_key_id')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropIndex(['hash_key_id']);
                $table->dropColumn('hash_key_id');
            });
        }
    }

    private function searchLogsTable(): string
    {
        return TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));
    }
};
