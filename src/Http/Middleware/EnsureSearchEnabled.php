<?php

declare(strict_types=1);

namespace Capell\Search\Http\Middleware;

use Capell\Search\Actions\ResolveSearchSettingAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The master "Enable search" switch turns the whole public surface off, not only the header
 * button: results, type-ahead and click tracking answer 404 while it is off.
 */
final class EnsureSearchEnabled
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) ResolveSearchSettingAction::run('enabled', 'capell-search.enabled', true), 404);

        return $next($request);
    }
}
