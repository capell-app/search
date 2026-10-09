<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Models\SearchLog;
use RuntimeException;

final class SeedSearchScreenshotWidgetAction
{
    public function handle(): void
    {
        throw_unless(app()->environment('local', 'testing') && getenv('CAPELL_SCREENSHOT_FIXTURE') === 'record-state', RuntimeException::class, 'Search screenshot fixtures require the disposable screenshot environment.');

        foreach ([
            'Screenshot publishing guide' => 8,
            'Screenshot missing guide' => 0,
            'Screenshot SEO audit' => 6,
            'Screenshot theme setup' => 5,
            'Screenshot block library' => 4,
            'Screenshot page editor' => 3,
        ] as $query => $results) {
            SearchLog::query()->updateOrCreate([
                'normalized_query' => mb_strtolower($query),
                'site_id' => null,
                'language_id' => null,
            ], [
                'query' => $query,
                'normalized_query_hash' => hash('sha256', mb_strtolower($query)),
                'results_count' => $results,
                'searched_at' => now()->subHour(),
            ]);
        }
    }
}
