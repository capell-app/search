<?php

declare(strict_types=1);

namespace Capell\Search\Support;

use Capell\Frontend\Facades\Frontend;
use Capell\Search\Actions\ResolveSearchSettingAction;

/**
 * The single answer to "is public search switched on?", for themes and other packages that render search forms.
 * The service provider registers it as a scoped binding, so the saved setting is read once per request (or queued
 * job) however many widgets ask, and nothing is held in static state. During a public render the value comes from
 * frontend data loaded before rendering, because public Blade must not query.
 */
final class PublicSearchSwitch
{
    private ?bool $enabled = null;

    public static function enabled(): bool
    {
        return resolve(self::class)->isEnabled();
    }

    /** The path of the public results page, without a leading slash. */
    public static function resultsPath(): string
    {
        $path = config('capell-search.route_path', 'search');

        return trim(is_string($path) ? $path : 'search', '/');
    }

    public function isEnabled(): bool
    {
        return $this->enabled ??= (bool) (Frontend::getFrontendData('search.enabled')
            ?? ResolveSearchSettingAction::run('enabled', 'capell-search.enabled', true));
    }
}
