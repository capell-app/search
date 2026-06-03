<?php

declare(strict_types=1);

use Capell\Frontend\Data\FrontendRuntimeManifestData;
use Capell\Frontend\Enums\RenderHookLocation;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Render\RenderHookRegistry;
use Capell\Search\Support\RenderHooks\RegisterHeaderSearchHook;

it('registers an icon-triggered header search modal', function (): void {
    Frontend::setFrontendData(
        'runtimeManifest',
        FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::FullLivewire),
    );

    $registry = new RenderHookRegistry;

    (new RegisterHeaderSearchHook($registry))->register();

    $html = $registry->renderAll(
        RenderHookLocation::HeaderAfter,
        scenario: 'foundation-theme-header-actions',
        target: 'capell-navigation::components.header.navigation',
    );

    expect($html)
        ->toContain('aria-controls="site-header-search-dialog"')
        ->toContain('role="dialog"')
        ->toContain('id="site-header-search-dialog"')
        ->toContain('role="combobox"')
        ->toContain('role="listbox"')
        ->toContain(route('capell-frontend.search.autocomplete'))
        ->toContain('data-site-search-trigger')
        ->toContain('setActiveResult(dialog, -1)')
        ->toContain('setPageInert(true)')
        ->toContain('trapFocus(event, dialog)')
        ->toContain('data-site-search-debounce-ms')
        ->toContain('scheduleFetchResults(input.closest(selectors.dialog))')
        ->toContain('cancelPendingSearch(dialog)')
        ->toContain('data-site-search-suggestions-template')
        ->not()->toContain('id="site-search-query"')
        ->not()->toContain('x-data')
        ->not()->toContain('x-ref')
        ->not()->toContain('capell-search');
});

test('header search hook renders a real package view', function (): void {
    Frontend::setFrontendData(
        'runtimeManifest',
        FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::FullLivewire),
    );

    $html = view('capell-search::components.header.search-modal')->render();

    expect($html)
        ->toContain('role="search"')
        ->toContain(route('capell-frontend.search'))
        ->toContain('type="search"')
        ->toContain(route('capell-frontend.search.autocomplete'));
});
