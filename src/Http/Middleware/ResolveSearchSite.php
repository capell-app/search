<?php

declare(strict_types=1);

namespace Capell\Search\Http\Middleware;

use Capell\Core\Data\SiteAccessContextData;
use Capell\Core\Exceptions\SiteDomainNotFoundException;
use Capell\Core\Exceptions\UrlSiteDomainNotFoundException;
use Capell\Core\Support\SiteAccess\SiteAccessPolicyRegistry;
use Capell\Frontend\Support\Loader\SiteLoader;
use Capell\Frontend\Support\Loader\SiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSearchSite
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            [$site, $language, $domain] = SiteResolver::resolve($request->url(), SiteLoader::getSites());
        } catch (SiteDomainNotFoundException|UrlSiteDomainNotFoundException) {
            abort(404);
        }

        abort_unless($site->status === true, 404);

        $policy = resolve(SiteAccessPolicyRegistry::class)->resolve(new SiteAccessContextData(
            request: $request,
            site: $site,
            siteDomain: $domain,
            user: $request->user(),
        ));
        abort_if($policy?->active === true || $policy?->configurationAvailable === false, 404);

        $request->attributes->set('site', $site);
        $request->attributes->set('language', $language);

        return $next($request);
    }
}
