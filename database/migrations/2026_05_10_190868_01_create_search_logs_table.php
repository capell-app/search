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
        $tableName = TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));

        if (TableName::existsAndIsOwned(Config::string('capell-search.logs.table_name', 'search_logs'), basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained(TableName::resolve('sites'))->nullOnDelete();
            $table->foreignId('language_id')->nullable()->index();
            $table->string('query');
            $table->string('normalized_query')->index();
            $table->string('normalized_query_hash', 64)->nullable()->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->string('clicked_result_url')->nullable();
            $table->string('clicked_result_hash', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->dateTime('searched_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs')));
    }
};
