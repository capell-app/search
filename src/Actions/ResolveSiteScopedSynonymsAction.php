<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The synonym map in force for one site: the shared synonyms plus that site's own
 * `capell-search.site_synonyms.<site id>` entries, so one site's vocabulary never reaches another.
 *
 * @method static array<array-key, mixed> run(?int $siteId = null)
 */
final class ResolveSiteScopedSynonymsAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<array-key, mixed>
     */
    public function handle(?int $siteId = null): array
    {
        $shared = ResolveSearchSettingAction::run('synonyms', 'capell-search.synonyms', []);
        $synonyms = is_array($shared) ? $shared : [];

        if ($siteId === null) {
            return $synonyms;
        }

        $perSite = config('capell-search.site_synonyms.' . $siteId, []);

        return is_array($perSite) ? [...$synonyms, ...$perSite] : $synonyms;
    }
}
