<?php

declare(strict_types=1);

namespace Capell\Search\Tests;

use Capell\Core\Facades\CapellCore;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Tests\Packages\PackagesTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Override;

abstract class SearchTestCase extends PackagesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('search_logs') && ! Schema::hasColumn('search_logs', 'normalized_query_hash')) {
            Schema::table('search_logs', static function (Blueprint $table): void {
                $table->string('normalized_query_hash', 64)->nullable()->index();
                $table->string('clicked_result_hash', 64)->nullable()->index();
            });
        }
    }

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            SearchServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(SearchServiceProvider::$packageName);
    }
}
