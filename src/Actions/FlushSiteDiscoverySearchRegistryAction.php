<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Invalidates every cached built-in page index registry by moving the cache version on, so a
 * publish, unpublish or edit is searchable (or gone) on the next request. Other packages that
 * contribute public URLs can call this when their own content changes.
 *
 * @method static void run()
 */
final class FlushSiteDiscoverySearchRegistryAction
{
    use AsFake;
    use AsObject;

    public const string VERSION_KEY = 'capell-search:site-discovery-registry-version';

    public static function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return is_int($version) ? $version : 1;
    }

    public function handle(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }
}
