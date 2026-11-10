<?php

declare(strict_types=1);

namespace Capell\Search\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/capell-search.php', 'capell-search');

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/search', __DIR__ . '/../../database/migrations', [
                '2026_05_10_190868_01_create_search_logs_table' => Config::string('capell-search.logs.table_name', 'search_logs'),
            ]);
        });
    }
}
