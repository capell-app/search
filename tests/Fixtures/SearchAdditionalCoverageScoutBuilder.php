<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Illuminate\Pagination\LengthAwarePaginator;

final class SearchAdditionalCoverageScoutBuilder
{
    /** @var array<string, mixed> */
    private array $wheres = [];

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        private readonly string $query,
        private readonly array $records,
    ) {}

    public function where(string $field, mixed $value): self
    {
        $this->wheres[$field] = $value;

        return $this;
    }

    /**
     * @return LengthAwarePaginator<array-key, mixed>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $records = collect($this->records)
            ->filter(fn (array $record): bool => $this->matchesQuery($record))
            ->filter(fn (array $record): bool => $this->matchesWheres($record))
            ->map(static fn (array $record): SearchAdditionalCoverageScoutRecord => new SearchAdditionalCoverageScoutRecord($record))
            ->values();

        $total = $records->max(static function (SearchAdditionalCoverageScoutRecord $record) use ($records): int {
            $attributes = $record->toArray();

            return is_numeric($attributes['__engine_total'] ?? null)
                ? (int) $attributes['__engine_total']
                : $records->count();
        });

        return new LengthAwarePaginator($records->all(), is_numeric($total) ? (int) $total : 0, $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesQuery(array $record): bool
    {
        $query = mb_strtolower($this->query);

        foreach (['title', 'excerpt', 'body'] as $field) {
            if (str_contains(mb_strtolower((string) ($record[$field] ?? '')), $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesWheres(array $record): bool
    {
        foreach ($this->wheres as $field => $value) {
            if (array_key_exists($field, $record) && $record[$field] !== $value) {
                return false;
            }
        }

        return true;
    }
}
