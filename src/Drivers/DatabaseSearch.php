<?php

declare(strict_types=1);

namespace Capell\Search\Drivers;

use Capell\Core\Data\Database\SqlFragment;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\DatabaseFullTextSearch;
use Capell\Search\Data\DatabaseSearchExpression;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Support\DatabaseFullText;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Default DB-backed search. Uses the Core database platform's native full-text
 * support when a compatible index exists, with its portable fallback otherwise.
 */
class DatabaseSearch implements Search
{
    private const int MINIMUM_QUERY_LENGTH = 2;

    private const int MINIMUM_PER_PAGE = 1;

    private const int MAXIMUM_PER_PAGE = 100;

    private const string FULL_TEXT_INDEX = 'capell_search_database_fulltext';

    /**
     * @param  list<string>  $columns  Columns to search against.
     * @param  array<string, int|float|string>  $columnWeights
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'pages',
        private readonly array $columns = ['title', 'excerpt', 'body'],
        private readonly array $columnWeights = [],
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
        $columns = array_values(array_filter(
            array_intersect($this->columns, $availableColumns),
            static fn (string $column): bool => $column !== '',
        ));

        if ($columns === [] || $this->requiresMissingPublishedStatusColumn($availableColumns)) {
            return new Paginator([], 0, $perPage, $page);
        }

        $builder = $this->db->table($this->table);
        $fullTextSearch = $this->fullTextSearch($this->db, $builder, $columns, $query);
        $fullTextSearch->predicate->applyWhere($builder);

        $this->applyContextFilters($builder, $availableColumns, $siteId, $languageId);
        $this->applySearchFilters($builder, $availableColumns, $filters);

        $total = (clone $builder)->count();

        $builder->select('*');
        (new SqlFragment(
            $fullTextSearch->relevance->sql . ' as search_score',
            $fullTextSearch->relevance->bindings,
        ))->applySelect($builder);

        $builder->orderByDesc(new Expression('search_score'));

        $rows = $builder
            ->forPage($page, $perPage)
            ->get();

        $results = (new Collection($rows))->map(function (object $row): SearchResultData {
            $title = (string) ($row->{$this->titleColumn} ?? '');
            $excerptRaw = (string) ($row->{$this->excerptColumn} ?? $row->{$this->bodyColumn} ?? '');
            $score = isset($row->search_score) && is_numeric($row->search_score)
                ? (float) $row->search_score
                : 0.0;

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

    private function columnWeight(string $column): float
    {
        $weight = $this->columnWeights[$column] ?? $this->columnWeights[mb_strtolower($column)] ?? 1.0;

        if (! is_numeric($weight)) {
            return 1.0;
        }

        $weight = (float) $weight;

        return is_finite($weight) ? max(0.0, $weight) : 1.0;
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
     * @param  non-empty-list<non-empty-string>  $columns
     */
    private function fullTextSearch(
        Connection $connection,
        Builder $builder,
        array $columns,
        string $query,
    ): DatabaseFullTextSearch {
        $grammar = $builder->getGrammar();
        $expressions = array_map(
            fn (string $column): DatabaseSearchExpression => new DatabaseSearchExpression(
                SqlFragment::raw($grammar->wrap($column)),
                $this->columnWeight($column),
            ),
            $columns,
        );

        return (new DatabaseFullText)->search(
            $connection,
            $this->table,
            self::FULL_TEXT_INDEX,
            $expressions,
            $query,
        );
    }
}
