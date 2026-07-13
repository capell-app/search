<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchVisitorIdentityData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateSearchVisitorIdentityAction
{
    use AsAction;

    public function handle(Request $request, int $siteId, ?CarbonImmutable $at = null): SearchVisitorIdentityData
    {
        return new SearchVisitorIdentityData(
            ipHash: $this->hash($request->ip(), $siteId, $at),
            userAgentHash: $this->hash($request->userAgent(), $siteId, $at),
        );
    }

    private function hash(?string $value, int $siteId, ?CarbonImmutable $at): ?string
    {
        if (! (bool) ResolveSearchSettingAction::run('hash_visitor_data', 'capell-search.hash_visitor_data', true)) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $secret = $this->secret($siteId, $at);

        return $secret === null ? null : hash_hmac('sha256', $value, $secret);
    }

    private function secret(int $siteId, ?CarbonImmutable $at): ?string
    {
        $configuredSecret = config('capell-search.visitor_hash_secret');
        $rootSecret = is_string($configuredSecret) && trim($configuredSecret) !== ''
            ? trim($configuredSecret)
            : config('app.key');

        if (! is_string($rootSecret) || trim($rootSecret) === '') {
            return null;
        }

        $rotationDays = config('capell-search.visitor_hash_rotation_days', 30);
        $resolvedRotationDays = is_numeric($rotationDays) && (int) $rotationDays > 0 ? (int) $rotationDays : 30;
        $rotationDate = $at ?? now()->toImmutable();
        $period = sprintf('%d:%d', $rotationDate->year, intdiv($rotationDate->dayOfYear - 1, $resolvedRotationDays));

        return hash_hmac('sha256', sprintf('capell-search:visitor:site:%d:period:%s', $siteId, $period), $rootSecret);
    }
}
