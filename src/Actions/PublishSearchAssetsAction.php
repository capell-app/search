<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Illuminate\Filesystem\Filesystem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static void run(?string $publicRoot = null)
 */
final class PublishSearchAssetsAction
{
    use AsFake;
    use AsObject;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    public function handle(?string $publicRoot = null): void
    {
        $source = dirname(__DIR__, 2) . '/resources/dist/search-modal.js';
        $destination = ($publicRoot ?? public_path()) . '/vendor/capell-search/search-modal.js';

        throw_unless($this->filesystem->isFile($source), RuntimeException::class, 'Search dialog distribution asset is missing.');

        $this->filesystem->ensureDirectoryExists(dirname($destination));
        throw_unless($this->filesystem->copy($source, $destination), RuntimeException::class, 'Unable to publish the Search dialog asset.');
    }
}
