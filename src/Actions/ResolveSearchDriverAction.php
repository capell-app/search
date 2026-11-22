<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Enums\SearchDriver;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Picks the driver behind /search.
 *
 * An explicit choice (a saved search driver other than the seeded default, or
 * a configured capell-search.driver) always wins. With none, a configured external Scout
 * engine is used automatically, and anything else falls back to the local
 * page catalogue.
 */
final class ResolveSearchDriverAction
{
    use AsFake;
    use AsObject;

    /** Scout engines that talk to an external provider. */
    private const array EXTERNAL_SCOUT_ENGINES = ['typesense', 'meilisearch', 'algolia'];

    public function handle(): string
    {
        if (config('capell-search.driver') !== null || $this->hasSavedDriverChoice()) {
            return $this->explicitDriver();
        }

        if ($this->usesExternalScoutEngine()) {
            return SearchDriver::Scout->value;
        }

        return SearchDriver::SiteDiscovery->value;
    }

    public function usesExternalScoutEngine(): bool
    {
        $engine = config('scout.driver');

        return is_string($engine) && in_array(mb_strtolower($engine), self::EXTERNAL_SCOUT_ENGINES, true);
    }

    /**
     * The settings migration seeds every site with `site_discovery`, so that
     * value alone is the default, not a choice. Any other saved driver is.
     */
    private function hasSavedDriverChoice(): bool
    {
        if (! ResolveSearchSettingAction::make()->hasPersistedSetting('driver')) {
            return false;
        }

        $saved = ResolveSearchSettingAction::run('driver', 'capell-search.driver', null);
        $saved = $saved instanceof SearchDriver ? $saved->value : $saved;

        return is_string($saved) && $saved !== '' && $saved !== SearchDriver::SiteDiscovery->value;
    }

    private function explicitDriver(): string
    {
        $driver = ResolveSearchSettingAction::run('driver', 'capell-search.driver', SearchDriver::SiteDiscovery->value);

        if ($driver instanceof SearchDriver) {
            return $driver->value;
        }

        return is_string($driver) && $driver !== '' ? $driver : SearchDriver::SiteDiscovery->value;
    }
}
