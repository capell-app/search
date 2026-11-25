<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The search_logs table as the package migrations leave it. Tests that rebuild
 * the table must use this: Eloquent caches a model's column list for the whole
 * process, so a hand-built table missing a migrated column makes later tests
 * fail mass assignment on that column.
 */
final class SearchLogsTable
{
    public static function recreate(): void
    {
        Schema::dropIfExists('search_logs');
        Schema::create('search_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            $table->foreignId('language_id')->nullable()->index();
            $table->string('query');
            $table->string('normalized_query')->index();
            $table->string('normalized_query_hash', 64)->nullable()->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->string('clicked_result_url')->nullable();
            $table->string('clicked_result_hash', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('hash_key_id', 16)->nullable()->index();
            $table->timestamp('searched_at')->index();
            $table->timestamps();
        });
    }
}
