<?php

declare(strict_types=1);

namespace Capell\Search\Settings;

use Capell\Core\Contracts\SettingsContract;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Spatie\LaravelSettings\Settings;

final class SearchSettings extends Settings implements SettingsContract
{
    public bool $enabled = true;

    public bool $show_header_search = true;

    public int $results_per_page = 10;

    public SearchDriver $driver = SearchDriver::Database;

    public bool $record_search_logs = true;

    public int $log_retention_days = 180;

    public bool $hash_visitor_data = true;

    public int $minimum_query_length = 2;

    /** @phpstan-var array<string, array<string, bool>> */
    public array $sources = [];

    public array $synonyms = [];

    public array $typo_corrections = [];

    public array $typo_terms = [];

    public int $typo_max_distance = 1;

    public array $promoted_results = [];

    public static function group(): string
    {
        return 'search';
    }

    public static function schema(): string
    {
        return SearchSettingsSchema::class;
    }
}
