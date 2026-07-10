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
