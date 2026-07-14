<?php

declare(strict_types=1);

use Capell\Search\Actions\PublishSearchAssetsAction;
use Illuminate\Filesystem\Filesystem;

it('publishes the external search dialog script during package installation', function (): void {
    $publicRoot = sys_get_temp_dir() . '/capell-search-assets-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem;

    try {
        PublishSearchAssetsAction::run($publicRoot);

        $publishedPath = $publicRoot . '/vendor/capell-search/search-modal.js';
        $sourcePath = dirname(__DIR__, 3) . '/resources/dist/search-modal.js';

        expect($publishedPath)->toBeFile()
            ->and(hash_file('sha256', $publishedPath))->toBe(hash_file('sha256', $sourcePath));
    } finally {
        $filesystem->deleteDirectory($publicRoot);
    }
});
