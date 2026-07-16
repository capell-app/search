<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Core\Models\Site;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class CanCollectSearchAnalyticsAction
{
    use AsFake;
    use AsObject;

    public function handle(Request $request): bool
    {
        if (! $request->attributes->get('site') instanceof Site) {
            return false;
        }

        if (config('capell-search.honor_privacy_signals', true) !== true) {
            return true;
        }

        return $request->headers->get('Sec-GPC') !== '1'
            && $request->headers->get('DNT') !== '1'
            && $request->headers->get('X-Do-Not-Track') !== '1';
    }
}
