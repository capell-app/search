<?php

declare(strict_types=1);

namespace Capell\Search\Models;

use Capell\Search\Database\Factories\SearchLogFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property int|null $site_id
 * @property int|null $language_id
 * @property string $query
 * @property string $normalized_query
 * @property int $results_count
 * @property string|null $clicked_result_url
 * @property string|null $ip_hash
 * @property string|null $user_agent_hash
 * @property CarbonImmutable $searched_at
 * @property int $searches
 */
final class SearchLog extends Model
{
    /** @use HasFactory<SearchLogFactory> */
    use HasFactory;

    protected static string $factory = SearchLogFactory::class;

    protected $guarded = [];

    #[Override]
    public function getTable(): string
    {
        return config('capell-search.logs.table_name', 'search_logs');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'query' => 'encrypted',
            'clicked_result_url' => 'encrypted',
            'searched_at' => 'immutable_datetime',
        ];
    }
}
