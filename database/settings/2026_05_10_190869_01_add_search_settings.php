<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'search.enabled' => true,
            'search.show_header_search' => true,
            'search.results_per_page' => 10,
            'search.driver' => 'database',
            'search.record_search_logs' => true,
            'search.log_retention_days' => 180,
            'search.hash_visitor_data' => true,
            'search.minimum_query_length' => 2,
            'search.sources' => [],
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }
};
