<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Search\Actions\CanCollectSearchAnalyticsAction;
use Illuminate\Http\Request;

it('fails closed without a trusted site or when a privacy signal is present', function (): void {
    $request = Request::create('/search');

    expect(CanCollectSearchAnalyticsAction::run($request))->toBeFalse();

    $request->attributes->set('site', Site::factory()->make(['id' => 1]));
    $request->headers->set('Sec-GPC', '1');

    expect(CanCollectSearchAnalyticsAction::run($request))->toBeFalse();
});

it('documents the browser privacy signals enforced by analytics collection', function (): void {
    config()->set('capell-search.honor_privacy_signals', true);
    $request = Request::create('/search');
    $request->attributes->set('site', Site::factory()->make(['id' => 1]));

    expect(CanCollectSearchAnalyticsAction::run($request))->toBeTrue();

    foreach (['Sec-GPC', 'DNT', 'X-Do-Not-Track'] as $header) {
        $request->headers->set($header, '1');
        expect(CanCollectSearchAnalyticsAction::run($request))->toBeFalse();
        $request->headers->remove($header);
    }

    $manifest = capell_json_file_array(__DIR__ . '/../../../capell.json');

    expect($manifest['description'])->toBeString()
        ->toContain('browser privacy signals')
        ->not->toContain('consent-aware');

    $narrative = capell_json_file_array(__DIR__ . '/../../../docs/readme-narrative.json');
    $readme = file_get_contents(__DIR__ . '/../../../README.md');

    expect(data_get($narrative, 'whatThisPluginAdds.text'))->toBeString()
        ->toContain('browser privacy signals')
        ->not->toContain('consent-aware')
        ->and($readme)->toBeString()
        ->toContain('browser privacy signals')
        ->not->toContain('consent-aware');
});
