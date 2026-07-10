<?php

declare(strict_types=1);

use Capell\Frontend\Data\FrontendRuntimeManifestData;
use Capell\Frontend\Enums\RenderHookLocation;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Render\RenderHookRegistry;
use Capell\Search\Support\RenderHooks\RegisterHeaderSearchHook;

test('search modal asset is generated from committed package source', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $packageManifest = json_decode(
        (string) file_get_contents($packageRoot . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $source = file_get_contents($packageRoot . '/resources/js/search-modal.js');
    $distribution = file_get_contents($packageRoot . '/resources/dist/search-modal.js');

    expect($packageManifest)->toBeArray()
        ->and(data_get($packageManifest, 'scripts.build'))->toBe('node build.mjs')
        ->and(data_get($packageManifest, 'scripts.build:check'))->toBe('node build.mjs --check')
        ->and($source)->toBeString()->toContain('window.siteSearchModalInitialized')
        ->and($distribution)->toBeString()
        ->toStartWith('// Generated from resources/js/search-modal.js.')
        ->toContain("mode: 'no-cors'");
});

it('registers an icon-triggered header search modal', function (): void {
    Frontend::setFrontendData(
        'runtimeManifest',
        FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::FullLivewire),
    );

    $registry = new RenderHookRegistry;

    $registry->registerExtension(
        location: RenderHookLocation::HeaderAfter,
        extension: new RegisterHeaderSearchHook,
    );

    $html = $registry->renderAll(RenderHookLocation::HeaderAfter);

    expect($html)
        ->toContain('aria-controls="site-header-search-dialog"')
        ->toContain('role="dialog"')
        ->toContain('id="site-header-search-dialog"')
        ->toContain('role="combobox"')
        ->toContain('role="listbox"')
        ->toContain(route('capell-frontend.search.autocomplete', absolute: false))
        ->toContain(route('capell-frontend.search.click', absolute: false))
        ->toContain('data-site-search-trigger')
        ->toContain('data-site-search-debounce-ms')
        ->toContain('data-site-search-suggestions-template')
        ->toContain('vendor/capell-search/search-modal.js')
        ->not()->toContain('id="site-search-query"')
        ->not()->toContain('x-data')
        ->not()->toContain('x-ref');
});

test('header search hook renders a real package view', function (): void {
    Frontend::setFrontendData(
        'runtimeManifest',
        FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::FullLivewire),
    );

    $html = view('capell-search::components.header.search-modal')->render();

    expect($html)
        ->toContain('role="search"')
        ->toContain(route('capell-frontend.search', absolute: false))
        ->toContain('type="search"')
        ->toContain('Search')
        ->toContain('placeholder="Search pages, extensions, resources, and media"')
        ->toContain('data-site-search-results')
        ->toContain('hidden')
        ->toContain(route('capell-frontend.search.autocomplete', absolute: false))
        ->toContain(route('capell-frontend.search.click', absolute: false));
});
