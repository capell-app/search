<?php

declare(strict_types=1);

namespace Capell\Search\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Result and facet pages are unbounded, low-value URLs: keep them out of the index and
 * point crawlers at the bare search page so they are not a crawl trap.
 */
final class MarkSearchPagesNoindex
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        // A relative canonical never reflects the request's Host header into a cacheable response.
        if ($response->isSuccessful()) {
            $response->headers->set('Link', '<' . $request->getPathInfo() . '>; rel="canonical"', false);
        }

        return $response;
    }
}
