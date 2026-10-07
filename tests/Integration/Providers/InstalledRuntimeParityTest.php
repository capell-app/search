<?php

declare(strict_types=1);

use Capell\Tests\Support\InstalledRuntimeParity;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('search', configureBoot: static function (): void {
        config()->set('capell-search.logs.hash_secret', 'search-installed-runtime-test-secret');
    });
});
