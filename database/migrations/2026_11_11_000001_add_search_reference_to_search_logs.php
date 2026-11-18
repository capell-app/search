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

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'search_reference')) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table): void {
            $table->string('search_reference', 26)->nullable()->index();
        });
    }

    public function down(): void
    {
        $tableName = $this->searchLogsTable();

        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'search_reference')) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropColumn('search_reference');
            });
        }
    }

    private function searchLogsTable(): string
    {
        return TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));
    }
};
