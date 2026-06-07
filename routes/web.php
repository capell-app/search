<?php

declare(strict_types=1);

use Capell\Search\Http\Controllers\SearchController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::name('capell-frontend.')
    ->middleware(['web', 'frontend.resolve'])
    ->group(function (): void {
        Route::get(config('capell-search.route_path', 'search'), SearchController::class)
            ->name('search');

        Route::get(config('capell-search.autocomplete.route_path', 'search/autocomplete'), [SearchController::class, 'autocomplete'])
            ->middleware('throttle:' . config('capell-search.autocomplete.rate_limiter', 'capell-search-autocomplete'))
            ->name('search.autocomplete');

        Route::post(config('capell-search.click_tracking.route_path', 'search/click'), [SearchController::class, 'click'])
            ->middleware('throttle:' . config('capell-search.click_tracking.rate_limiter', 'capell-search-clicks'))
            ->withoutMiddleware([VerifyCsrfToken::class])
            ->name('search.click');
    });
