<?php

declare(strict_types=1);

use Capell\Core\Data\SiteDomains\SiteRequestTargetData;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Data\FrontendRuntimeManifestData;
use Capell\Frontend\Enums\RenderHookLocation;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Render\RenderHookRegistry;
use Capell\Search\Settings\SearchSettings;
use Capell\Search\Support\RenderHooks\RegisterHeaderSearchHook;
use Capell\Tests\Support\JavaScriptScenario;

it('records one anonymous click without delaying navigation from either shipped search module', function (): void {
    $root = dirname(__DIR__, 2);
    foreach (['resources/js/search-modal.js', 'resources/dist/search-modal.js'] as $module) {
        JavaScriptScenario::run($root . '/tests/Support/click-beacon-outcomes.mjs', $root . '/' . $module);
    }
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

it('hides and restores only the header integration when the enable setting changes', function (): void {
    $this->registerAndMigrateSettings(['2026_05_10_190869_01_add_search_settings'], dirname(__DIR__, 2) . '/database/settings');
    $origin = SiteRequestTargetData::fromUrl(route('capell-frontend.search'))->origin;
    SiteDomain::factory()->createOne(['domain' => $origin->host, 'scheme' => $origin->scheme, 'port' => $origin->effectivePort, 'path' => null]);
    $registry = new RenderHookRegistry;
    $registry->registerExtension(location: RenderHookLocation::HeaderAfter, extension: new RegisterHeaderSearchHook);
    foreach ([false, true] as $enabled) {
        $settings = resolve(SearchSettings::class);
        $settings->enabled = $enabled;
        $settings->show_header_search = true;
        $settings->save();
        Frontend::setFrontendData('search.header.enabled', null);
        Frontend::setFrontendData('search.header.visible', null);
        $html = $registry->renderAll(RenderHookLocation::HeaderAfter);
        config()->set('capell-search.page_view', 'capell-search::components.form');
        $this->get(route('capell-frontend.search', ['q' => 'Capell'], false))->assertOk()->assertSee('type="search"', false);
        $this->getJson(route('capell-frontend.search.autocomplete', ['q' => 'Capell'], false))->assertOk()->assertJsonPath('query', 'Capell')->assertJsonStructure(['results']);
        if ($enabled) {
            expect($html)->toContain('data-site-search-trigger');
        } else {
            expect($html)->toBe('');
        }
    }
});
