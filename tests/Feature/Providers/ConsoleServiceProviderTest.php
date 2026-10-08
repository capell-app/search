<?php

declare(strict_types=1);

use Capell\Core\Support\Manifest\CapellManifestData;
use Capell\Search\Providers\SearchServiceProvider;
use Symfony\Component\Process\Process;

it('preserves the main provider as the manifest package identity', function (): void {
    $manifest = capell_json_file_array(__DIR__ . '/../../../capell.json');

    expect(CapellManifestData::fromArray($manifest)->serviceProviderClass())
        ->toBe(SearchServiceProvider::class);
});

it('registers every advertised console command from install providers alone', function (string $timing): void {
    $root = dirname(__DIR__, 5);
    $source = <<<'PHP'
    declare(strict_types=1);

    require $argv[1] . '/vendor/autoload.php';

    $manifest = json_decode(file_get_contents($argv[1] . '/packages/search/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    $app = new Illuminate\Foundation\Application($argv[1]);
    $app->instance('config', new Illuminate\Config\Repository);
    $app->instance('env', 'testing');
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    $events = new Illuminate\Events\Dispatcher($app);
    $app->instance('events', $events);
    $kernel = new Illuminate\Foundation\Console\Kernel($app, $events);
    $app->instance(Illuminate\Contracts\Console\Kernel::class, $kernel);

    if ($argv[2] === 'after startup') {
        $app->boot();
        $app->bootstrapWith([]);
        $kernel->all();
    }

    foreach ($manifest['providers']['install'] as $provider) {
        $app->register($provider);
    }

    $app->boot();
    $app->bootstrapWith([]);
    $commandName = $manifest['commands']['screenshotFixture'];
    $command = $kernel->all()[$commandName] ?? null;

    if (! $command instanceof Capell\Search\Console\Commands\SeedSearchScreenshotFixtureCommand) {
        throw new RuntimeException('The Search screenshot fixture is unavailable ' . $argv[2] . ' with only install providers.');
    }

    foreach ($manifest['commands'] as $name) {
        if (is_string($name) && ! ($kernel->all()[$name] ?? null) instanceof Illuminate\Console\Command) {
            throw new RuntimeException('Advertised Search command [' . $name . '] is unavailable ' . $argv[2] . '.');
        }
    }

    foreach ($manifest['providers']['install'] as $provider) {
        $app->register($provider, true);
    }

    foreach ($manifest['commands'] as $name) {
        if (is_string($name) && ! ($kernel->all()[$name] ?? null) instanceof Illuminate\Console\Command) {
            throw new RuntimeException('Repeated install-provider registration lost Search command [' . $name . '].');
        }
    }

    if ($app->getProvider(Capell\Search\Providers\SearchServiceProvider::class) !== null
        || $app->getProvider(Capell\Search\Providers\AdminServiceProvider::class) !== null) {
        throw new RuntimeException('Fixture registration must not boot Search runtime or admin providers.');
    }
    PHP;
    $process = new Process([PHP_BINARY, '-r', $source, $root, $timing], $root);
    $process->setTimeout(30);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput());
})->with(['before startup', 'after startup']);
