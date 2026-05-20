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
        ->toContain('x-ref="searchInput"')
        ->not()->toContain('id="site-search-query"')
        ->not()->toContain('capell-search');
});
