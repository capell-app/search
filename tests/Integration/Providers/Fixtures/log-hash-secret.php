<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;

// Search logs hash visitor terms with this secret. The parity test and the baseline generator
// share this file, so the seeded key belongs to the background in both.
return static function (Application $app): void {
    $app->make(Repository::class)->set('capell-search.logs.hash_secret', 'search-installed-runtime-test-secret');
};
