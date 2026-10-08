<?php

declare(strict_types=1);

namespace Capell\Search\Providers;

use Capell\Search\Console\Commands\FlushSearchCommand;
use Capell\Search\Console\Commands\IndexSearchCommand;
use Capell\Search\Console\Commands\PurgeSearchLogsCommand;
use Capell\Search\Console\Commands\SeedSearchScreenshotFixtureCommand;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Override;

final class ConsoleServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            FlushSearchCommand::class,
            IndexSearchCommand::class,
            PurgeSearchLogsCommand::class,
            SeedSearchScreenshotFixtureCommand::class,
        ]);

        // Install providers can arrive after the installer's Artisan instance starts.
        if ($this->app instanceof Application && $this->app->isBooted()) {
            Artisan::registerCommand($this->app->make(FlushSearchCommand::class));
            Artisan::registerCommand($this->app->make(IndexSearchCommand::class));
            Artisan::registerCommand($this->app->make(PurgeSearchLogsCommand::class));
            Artisan::registerCommand($this->app->make(SeedSearchScreenshotFixtureCommand::class));
        }
    }
}
