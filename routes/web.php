<?php

declare(strict_types=1);

use Capell\Search\Http\Controllers\SearchController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::name('capell-frontend.')
    ->middleware(['web', 'frontend.resolve'])
    ->group(function (): void {
        $searchRoutePath = config('capell-search.route_path', 'search');
        $searchRateLimiter = config('capell-search.rate_limiter', 'capell-search-requests');
        $autocompleteRoutePath = config('capell-search.autocomplete.route_path', 'search/autocomplete');
        $autocompleteRateLimiter = config('capell-search.autocomplete.rate_limiter', 'capell-search-autocomplete');
        $clickRoutePath = config('capell-search.click_tracking.route_path', 'search/click');
        $clickRateLimiter = config('capell-search.click_tracking.rate_limiter', 'capell-search-clicks');

        Route::get(is_string($searchRoutePath) ? $searchRoutePath : 'search', SearchController::class)
            ->middleware('throttle:' . (is_string($searchRateLimiter) ? $searchRateLimiter : 'capell-search-requests'))
            ->name('search');

        Route::get(is_string($autocompleteRoutePath) ? $autocompleteRoutePath : 'search/autocomplete', [SearchController::class, 'autocomplete'])
            ->middleware('throttle:' . (is_string($autocompleteRateLimiter) ? $autocompleteRateLimiter : 'capell-search-autocomplete'))
            ->name('search.autocomplete');

        Route::post(is_string($clickRoutePath) ? $clickRoutePath : 'search/click', [SearchController::class, 'click'])
            ->middleware('throttle:' . (is_string($clickRateLimiter) ? $clickRateLimiter : 'capell-search-clicks'))
            ->withoutMiddleware([VerifyCsrfToken::class])
            ->name('search.click');
    });
