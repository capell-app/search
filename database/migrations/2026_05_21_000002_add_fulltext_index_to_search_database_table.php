<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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

        $grammar = DB::connection()->getQueryGrammar();
        $tableName = (string) config('capell-search.database.table', 'pages');
        $columns = $this->searchColumns($tableName);

        if ($columns === []) {
            return;
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE %s ADD FULLTEXT %s (%s)',
                $grammar->wrapTable($tableName),
                $grammar->wrap($this->indexName),
                collect($columns)->map(static fn (string $column): string => $grammar->wrap($column))->implode(', '),
            ));
        } catch (Throwable) {
            // Existing installations may already have a compatible fulltext index.
        }
    }

    public function down(): void
    {
        if (! $this->canManageFullTextIndex()) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $tableName = (string) config('capell-search.database.table', 'pages');

        try {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP INDEX %s',
                $grammar->wrapTable($tableName),
                $grammar->wrap($this->indexName),
            ));
        } catch (Throwable) {
            // Index may not exist on this connection.
        }
    }

    private function canManageFullTextIndex(): bool
    {
        $tableName = (string) config('capell-search.database.table', 'pages');

        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            && Schema::hasTable($tableName);
    }

    /**
     * @return list<string>
     */
    private function searchColumns(string $tableName): array
    {
        $configuredColumns = config('capell-search.database.columns', ['title', 'excerpt', 'body']);
        $configuredColumns = is_array($configuredColumns) ? $configuredColumns : [];

        $availableColumns = Schema::getColumnListing($tableName);

        return array_values(array_filter(
            $configuredColumns,
            static fn (mixed $column): bool => is_string($column) && in_array($column, $availableColumns, true),
        ));
    }
};
