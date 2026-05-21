<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->canAddForeignKey()) {
            return;
        }

        DB::table('search_logs')
            ->whereNotNull('site_id')
            ->whereNotIn('site_id', DB::table('sites')->select('id'))
            ->update(['site_id' => null]);

        try {
            Schema::table('search_logs', function (Blueprint $table): void {
                $table->foreign('site_id', 'search_logs_site_id_foreign')
                    ->references('id')
                    ->on('sites')
                    ->nullOnDelete();
            });
        } catch (Throwable) {
            // Existing installations may already have the retrofit constraint.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('search_logs') || ! Schema::hasColumn('search_logs', 'site_id')) {
            return;
        }

        try {
            Schema::table('search_logs', function (Blueprint $table): void {
                $table->dropForeign('search_logs_site_id_foreign');
            });
        } catch (Throwable) {
            // Constraint may not exist on this connection.
        }
    }

    private function canAddForeignKey(): bool
    {
        return DB::connection()->getDriverName() !== 'sqlite'
            && Schema::hasTable('sites')
            && Schema::hasTable('search_logs')
            && Schema::hasColumn('search_logs', 'site_id');
    }
};
