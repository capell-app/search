<?php

declare(strict_types=1);

use Capell\Search\Support\DatabaseFullText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'capell_search_database_fulltext';

    public function up(): void
    {
        if (! $this->canManageFullTextIndex()) {
            return;
        }

        $tableName = (string) config('capell-search.database.table', 'pages');
        $columns = $this->searchColumns($tableName);

        if ($columns === []) {
            return;
        }

        $connection = Schema::getConnection();
        $fullText = new DatabaseFullText;

        try {
            $index = $fullText->createIndex($connection, $tableName, $this->indexName, $columns);

            if ($index !== null) {
                DB::statement($index->sql, $index->bindings);
            }
        } catch (Throwable) {
            // Existing installations may already have a compatible fulltext index.
        }
    }

    public function down(): void
    {
        if (! $this->canManageFullTextIndex()) {
            return;
        }

        $tableName = (string) config('capell-search.database.table', 'pages');

        try {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex($this->indexName);
            });
        } catch (Throwable) {
            // Index may not exist on this connection.
        }
    }

    private function canManageFullTextIndex(): bool
    {
        $tableName = (string) config('capell-search.database.table', 'pages');
        $connection = Schema::getConnection();

        return (new DatabaseFullText)->supportsIndex($connection)
            && Schema::hasTable($tableName);
    }

    /**
     * @return list<non-empty-string>
     */
    private function searchColumns(string $tableName): array
    {
        $configuredColumns = config('capell-search.database.columns', ['title', 'excerpt', 'body']);
        $configuredColumns = is_array($configuredColumns) ? $configuredColumns : [];

        $availableColumns = Schema::getColumnListing($tableName);

        return array_values(array_filter(
            $configuredColumns,
            static fn (mixed $column): bool => is_string($column)
                && $column !== ''
                && in_array($column, $availableColumns, true),
        ));
    }
};
