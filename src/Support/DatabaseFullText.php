<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Capell\Core\Data\Database\SqlFragment;
use Capell\Search\Data\DatabaseFullTextSearch;
use Capell\Search\Data\DatabaseSearchExpression;
use Illuminate\Database\Connection;
use Throwable;

final class DatabaseFullText
{
    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     */
    public function search(
        Connection $connection,
        string $table,
        string $index,
        array $expressions,
        string $query,
    ): DatabaseFullTextSearch {
        $fallback = $this->fallback($expressions, $query);
        $terms = $this->terms($query);

        if ($terms === [] || ! $this->hasCompatibleIndex($connection, $table, $index, $expressions)) {
            return $fallback;
        }

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysql($expressions, $terms, $fallback),
            'pgsql' => $this->postgres($expressions, $terms, $fallback),
            default => $fallback,
        };
    }

    /**
     * @param  non-empty-list<non-empty-string>  $columns
     */
    public function createIndex(
        Connection $connection,
        string $table,
        string $index,
        array $columns,
    ): ?SqlFragment {
        $grammar = $connection->getQueryGrammar();
        $wrappedTable = $grammar->wrapTable($table);
        $wrappedIndex = $grammar->wrap($index);
        $wrappedColumns = array_map($grammar->wrap(...), $columns);

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => new SqlFragment(sprintf(
                'ALTER TABLE %s ADD FULLTEXT %s (%s)',
                $wrappedTable,
                $wrappedIndex,
                implode(', ', $wrappedColumns),
            )),
            'pgsql' => new SqlFragment(sprintf(
                "CREATE INDEX %s ON %s USING GIN (to_tsvector('simple', %s))",
                $wrappedIndex,
                $wrappedTable,
                implode(" || ' ' || ", array_map(
                    static fn (string $column): string => sprintf("COALESCE(%s, '')", $column),
                    $wrappedColumns,
                )),
            )),
            default => null,
        };
    }

    public function supportsIndex(Connection $connection): bool
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }

    private static function escapeBooleanTerm(string $term): string
    {
        return str_replace(
            ['\\', '+', '-', '>', '<', '(', ')', '~', '*', '"', '@'],
            ['\\\\', '\+', '\-', '\>', '\<', '\(', '\)', '\~', '\*', '\"', '\@'],
            $term,
        );
    }

    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     */
    private function hasCompatibleIndex(
        Connection $connection,
        string $table,
        string $index,
        array $expressions,
    ): bool {
        if (! $this->supportsIndex($connection)) {
            return false;
        }

        try {
            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                $requiredColumns = array_map(
                    static fn (DatabaseSearchExpression $expression): string => strtolower(
                        trim($expression->expression->sql, '`" '),
                    ),
                    $expressions,
                );
                sort($requiredColumns);

                foreach ($connection->getSchemaBuilder()->getIndexes($table) as $existingIndex) {
                    $type = $existingIndex['type'];
                    $columns = $existingIndex['columns'];

                    if (! is_string($type) || strtolower($type) !== 'fulltext' || ! is_array($columns)) {
                        continue;
                    }

                    $columns = array_map(static fn (mixed $column): string => strtolower((string) $column), $columns);
                    sort($columns);

                    if ($columns === $requiredColumns) {
                        return true;
                    }
                }

                return false;
            }

            return $connection->table('pg_catalog.pg_class as index_class')
                ->join('pg_catalog.pg_index as index_metadata', 'index_metadata.indexrelid', '=', 'index_class.oid')
                ->join('pg_catalog.pg_class as table_class', 'table_class.oid', '=', 'index_metadata.indrelid')
                ->where('table_class.relname', $table)
                ->where('index_class.relname', $index)
                ->where('index_metadata.indisvalid', true)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     */
    private function fallback(array $expressions, string $query): DatabaseFullTextSearch
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return new DatabaseFullTextSearch(
                predicate: new SqlFragment('0 = 1'),
                relevance: new SqlFragment('0'),
                native: false,
            );
        }

        $predicateSql = [];
        $predicateBindings = [];
        $relevanceSql = [];
        $relevanceBindings = [];

        foreach ($terms as $term) {
            $termPredicateSql = [];
            $pattern = '%' . $this->escapeLike($term) . '%';

            foreach ($expressions as $searchExpression) {
                $expression = $searchExpression->expression;
                $match = sprintf("LOWER(COALESCE(%s, '')) LIKE ? ESCAPE '!'", $expression->sql);
                $termPredicateSql[] = $match;
                $predicateBindings = [...$predicateBindings, ...$expression->bindings, $pattern];

                if ($searchExpression->weight === 0.0) {
                    continue;
                }

                $relevanceSql[] = sprintf('CASE WHEN %s THEN ? ELSE 0 END', $match);
                $relevanceBindings = [
                    ...$relevanceBindings,
                    ...$expression->bindings,
                    $pattern,
                    $searchExpression->weight,
                ];
            }

            $predicateSql[] = '(' . implode(' OR ', $termPredicateSql) . ')';
        }

        return new DatabaseFullTextSearch(
            predicate: new SqlFragment(implode(' AND ', $predicateSql), $predicateBindings),
            relevance: new SqlFragment(
                $relevanceSql === [] ? '0' : implode(' + ', $relevanceSql),
                $relevanceBindings,
            ),
            native: false,
        );
    }

    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     * @param  non-empty-list<non-empty-string>  $terms
     */
    private function mysql(
        array $expressions,
        array $terms,
        DatabaseFullTextSearch $fallback,
    ): DatabaseFullTextSearch {
        $columns = implode(', ', array_map(
            static fn (DatabaseSearchExpression $expression): string => $expression->expression->sql,
            $expressions,
        ));
        $booleanQuery = implode(' ', array_map(
            static fn (string $term): string => '+' . self::escapeBooleanTerm($term) . '*',
            $terms,
        ));

        return new DatabaseFullTextSearch(
            predicate: new SqlFragment(
                sprintf('MATCH (%s) AGAINST (? IN BOOLEAN MODE)', $columns),
                [...$this->expressionBindings($expressions), $booleanQuery],
            ),
            relevance: $fallback->relevance,
            native: true,
        );
    }

    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     * @param  non-empty-list<non-empty-string>  $terms
     */
    private function postgres(
        array $expressions,
        array $terms,
        DatabaseFullTextSearch $fallback,
    ): DatabaseFullTextSearch {
        $document = implode(" || ' ' || ", array_map(
            static fn (DatabaseSearchExpression $expression): string => sprintf(
                "COALESCE(%s, '')",
                $expression->expression->sql,
            ),
            $expressions,
        ));
        $prefixQuery = implode(' & ', array_map(
            static fn (string $term): string => "'" . str_replace(
                ['\\', "'"],
                ['\\\\', "''"],
                $term,
            ) . "':*",
            $terms,
        ));
        $vector = sprintf("to_tsvector('simple', %s)", $document);

        return new DatabaseFullTextSearch(
            predicate: new SqlFragment(
                sprintf("%s @@ to_tsquery('simple', ?)", $vector),
                [...$this->expressionBindings($expressions), $prefixQuery],
            ),
            relevance: $fallback->relevance,
            native: true,
        );
    }

    /**
     * @param  non-empty-list<DatabaseSearchExpression>  $expressions
     * @return list<mixed>
     */
    private function expressionBindings(array $expressions): array
    {
        return array_values(array_merge(...array_map(
            static fn (DatabaseSearchExpression $expression): array => $expression->expression->bindings,
            $expressions,
        )));
    }

    /**
     * @return list<non-empty-string>
     */
    private function terms(string $query): array
    {
        $terms = preg_split('/\s+/u', mb_strtolower(trim($query)));

        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $terms,
            static fn (string $term): bool => $term !== '',
        )));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
