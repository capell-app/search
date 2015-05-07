<?php

declare(strict_types=1);

namespace Capell\Search\Console\Commands;

use Capell\Search\Actions\SeedSearchScreenshotFixtureAction;
use Illuminate\Console\Command;

final class SeedSearchScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:search:screenshot-fixture {--force}';

    protected $description = 'Prepare a discoverable public page for Search screenshots.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        app(SeedSearchScreenshotFixtureAction::class)->handle();

        return self::SUCCESS;
    }
}
