<?php

declare(strict_types=1);

use Capell\Search\Actions\CreateSearchVisitorIdentityAction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('derives rotating visitor hashes per site before deferred collection', function (): void {
    config()->set('app.key', 'base64:application-key');
    config()->set('capell-search.visitor_hash_rotation_days', 30);
    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Capell Test Browser',
    ]);

    $action = app(CreateSearchVisitorIdentityAction::class);
    $first = $action->handle($request, 1, CarbonImmutable::parse('2026-07-01'));
    $samePeriod = $action->handle($request, 1, CarbonImmutable::parse('2026-07-15'));
    $otherSite = $action->handle($request, 2, CarbonImmutable::parse('2026-07-15'));
    $nextPeriod = $action->handle($request, 1, CarbonImmutable::parse('2026-08-01'));

    expect($first->ipHash)->toBe($samePeriod->ipHash)
        ->and($first->ipHash)->not->toBe($otherSite->ipHash)
        ->and($first->ipHash)->not->toBe($nextPeriod->ipHash);
});
