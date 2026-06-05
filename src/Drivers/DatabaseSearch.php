<?php

declare(strict_types=1);

namespace Capell\Search\Drivers;

use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Default DB-backed search. Runs a LIKE query against the configured table and
 * columns. Suitable as a fallback until a site wires in Scout or Meilisearch.
 */
class DatabaseSearch implements Search
{
    private const int MINIMUM_QUERY_LENGTH = 2;

    private const int MINIMUM_PER_PAGE = 1;

    private const int MAXIMUM_PER_PAGE = 100;

    /**
     * @param  list<string>  $columns  Columns to search against.
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'pages',
        private readonly array $columns = ['title', 'excerpt', 'body'],
        private readonly string $urlColumn = 'slug',
        private readonly string $typeColumn = 'type',
        private readonly string $titleColumn = 'title',
        private readonly string $excerptColumn = 'excerpt',
        private readonly string $bodyColumn = 'body',
        private readonly string $siteColumn = 'site_id',
        private readonly string $languageColumn = 'language_id',
        private readonly string $statusColumn = 'status',
        private readonly string|int|bool|null $publishedStatus = 'published',
    ) {}

    public function search(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
    ): LengthAwarePaginator {
        $query = trim($query);
        $perPage = max(self::MINIMUM_PER_PAGE, min(self::MAXIMUM_PER_PAGE, $perPage));
        $page = max(1, $page);

        if ($query === '' || mb_strlen($query) < self::MINIMUM_QUERY_LENGTH) {
            return new Paginator([], 0, $perPage, $page);
        }

        if (! $this->db instanceof Connection) {
            return new Paginator([], 0, $perPage, $page);
        }

        $availableColumns = $this->db->getSchemaBuilder()->getColumnListing($this->table);
        $columns = array_values(array_intersect($this->columns, $availableColumns));

        if ($columns === [] || $this->requiresMissingPublishedStatusColumn($availableColumns)) {
            return new Paginator([], 0, $perPage, $page);
        }

        $likeQuery = '%' . $this->escapeLike($query) . '%';
        $builder = $this->db->table($this->table);
        $fullTextQuery = $this->fullTextQuery($query);
        $usesFullText = $fullTextQuery !== '' && $this->canUseFullText($columns);

        if ($usesFullText) {
            $builder->whereRaw($this->fullTextMatchSql($columns), [$fullTextQuery]);
        } else {
            $builder->where(function (Builder $queryBuilder) use ($columns, $likeQuery): void {
                foreach ($columns as $column) {
                    $queryBuilder->orWhereRaw(
                        $queryBuilder->getGrammar()->wrap($column) . " LIKE ? ESCAPE '!'",
                        [$likeQuery],
                    );
                }
            });
        }

        $this->applyContextFilters($builder, $availableColumns, $siteId, $languageId);
        $this->applySearchFilters($builder, $availableColumns, $filters);

        $total = (clone $builder)->count();

        if ($usesFullText) {
            $builder->select('*')->selectRaw($this->fullTextScoreSql($columns), [$query]);
            $builder->orderByDesc(new Expression('search_score'));
        }

        $rows = $builder
            ->forPage($page, $perPage)
            ->get();

        $results = (new Collection($rows))->map(function (object $row) use ($query): SearchResultData {
            $title = (string) ($row->{$this->titleColumn} ?? '');
            $excerptRaw = (string) ($row->{$this->excerptColumn} ?? $row->{$this->bodyColumn} ?? '');
            $score = isset($row->search_score) && is_numeric($row->search_score)
                ? (float) $row->search_score
                : $this->score($title . ' ' . $excerptRaw, $query);

            return new SearchResultData(
                title: $title,
                url: '/' . ltrim((string) ($row->{$this->urlColumn} ?? ''), '/'),
                excerpt: $this->truncate($excerptRaw, 200),
                type: (string) ($row->{$this->typeColumn} ?? 'page'),
                score: $score,
                typeLabel: null,
                updatedAt: isset($row->updated_at) ? CarbonImmutable::parse($row->updated_at) : null,
            );
        });

        return new Paginator($results, $total, $perPage, $page);
    }

    public function highlight(string $text, string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '/(' . preg_quote($query, '/') . ')/i';

        return (string) preg_replace($pattern, '<mark>$1</mark>', $escaped);
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length)) . '...';
    }

    private function escapeLike(string $query): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
    }

    private function score(string $haystack, string $needle): float
    {
        $count = substr_count(mb_strtolower($haystack), mb_strtolower($needle));

        return (float) $count;
    }

    /**
     * @param  list<string>  $availableColumns
     */
    private function applyContextFilters(Builder $builder, array $availableColumns, ?int $siteId, ?int $languageId): void
    {
        if ($siteId !== null && in_array($this->siteColumn, $availableColumns, true)) {
            $builder->where($this->siteColumn, $siteId);
        }

        if ($languageId !== null && in_array($this->languageColumn, $availableColumns, true)) {
            $builder->where($this->languageColumn, $languageId);
        }

        if ($this->publishedStatus !== null && in_array($this->statusColumn, $availableColumns, true)) {
            $builder->where($this->statusColumn, $this->publishedStatus);
        }
    }

    /**
     * @param  list<string>  $availableColumns
     */
    private function requiresMissingPublishedStatusColumn(array $availableColumns): bool
    {
        return $this->publishedStatus !== null && ! in_array($this->statusColumn, $availableColumns, true);
    }

    /**
     * @param  list<string>  $availableColumns
     */
    private function applySearchFilters(Builder $builder, array $availableColumns, ?SearchFilterData $filters): void
    {
        if (! $filters instanceof SearchFilterData || $filters->types === [] || ! in_array($this->typeColumn, $availableColumns, true)) {
            return;
        }

        $builder->whereIn($this->typeColumn, $filters->types);
    }

    /**
     * @param  list<string>  $columns
     */
    private function canUseFullText(array $columns): bool
    {
        $connection = $this->db;

        if (! $connection instanceof Connection || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            $databaseName = $connection->getDatabaseName();
            $indexedColumns = $connection->table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $databaseName)
                ->where('TABLE_NAME', $this->table)
                ->where('INDEX_TYPE', 'FULLTEXT')
                ->orderBy('SEQ_IN_INDEX')
                ->get(['INDEX_NAME', 'COLUMN_NAME'])
                ->groupBy('INDEX_NAME')
                ->map(static fn (Collection $indexColumns): array => $indexColumns
                    ->pluck('COLUMN_NAME')
                    ->map(static fn (mixed $column): string => (string) $column)
                    ->all());

            return $indexedColumns->contains(
                static fn (array $indexColumns): bool => $indexColumns === $columns,
            );
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function fullTextMatchSql(array $columns): string
    {
        return sprintf('MATCH (%s) AGAINST (? IN BOOLEAN MODE)', $this->wrappedColumns($columns));
    }

    /**
     * @param  list<string>  $columns
     */
    private function fullTextScoreSql(array $columns): string
    {
        return sprintf('MATCH (%s) AGAINST (?) as search_score', $this->wrappedColumns($columns));
    }

    /**
     * @param  list<string>  $columns
     */
    private function wrappedColumns(array $columns): string
    {
        $connection = $this->db;

        if (! $connection instanceof Connection) {
            return collect($columns)->implode(', ');
        }

        return collect($columns)
            ->map(fn (string $column): string => $connection->getQueryGrammar()->wrap($column))
            ->implode(', ');
    }

    private function fullTextQuery(string $query): string
    {
        $terms = preg_split('/\s+/', $query) ?: [];

        return collect($terms)
            ->map(static fn (string $term): string => trim($term, '+-><()~*"@'))
            ->filter(static fn (string $term): bool => $term !== '')
            ->map(static fn (string $term): string => '+' . $term . '*')
            ->implode(' ');
    }
}
