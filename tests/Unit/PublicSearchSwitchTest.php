<?php

declare(strict_types=1);

use Capell\Frontend\Facades\Frontend;
use Capell\Search\Settings\SearchSettings;
use Capell\Search\Support\PublicSearchSwitch;
use Illuminate\Support\Facades\DB;

it('lets the saved Enable search setting override the config value', function (): void {
    $this->registerAndMigrateSettings(['2026_05_10_190869_01_add_search_settings'], dirname(__DIR__, 2) . '/database/settings');
    config(['capell-search.enabled' => true]);

    $settings = resolve(SearchSettings::class);
    $settings->enabled = false;
    $settings->save();
    app()->forgetScopedInstances();

    expect(PublicSearchSwitch::enabled())->toBeFalse();

    $settings->enabled = true;
    $settings->save();
    app()->forgetScopedInstances();

    expect(PublicSearchSwitch::enabled())->toBeTrue();
});

it('reads the setting once per request however many widgets ask', function (): void {
    $this->registerAndMigrateSettings(['2026_05_10_190869_01_add_search_settings'], dirname(__DIR__, 2) . '/database/settings');
    app()->forgetScopedInstances();

    DB::enableQueryLog();
    PublicSearchSwitch::enabled();
    $single = count(DB::getQueryLog());

    DB::flushQueryLog();
    app()->forgetScopedInstances();
    foreach (range(1, 25) as $widget) {
        PublicSearchSwitch::enabled();
    }

    expect(count(DB::getQueryLog()))->toBe($single)
        ->and(resolve(PublicSearchSwitch::class))->toBe(resolve(PublicSearchSwitch::class));
});

it('reports the configured results path without slashes', function (): void {
    config(['capell-search.route_path' => '/find/']);

    expect(PublicSearchSwitch::resultsPath())->toBe('find');
});

it('uses the value loaded before rendering, so public Blade never queries', function (): void {
    Frontend::setFrontendData('search.enabled', false);
    app()->forgetScopedInstances();
    DB::enableQueryLog();

    expect(PublicSearchSwitch::enabled())->toBeFalse()
        ->and(DB::getQueryLog())->toBe([]);
});
