<?php

declare(strict_types=1);

namespace Capell\Search\Upgrade;

use Capell\Core\Data\UpgradeContext;
use Capell\Core\Support\Upgrade\AbstractUpgradeStep;
use Capell\Search\Actions\RehashSearchLogsAction;
use Override;

/**
 * Re-keys search log hashes written under APP_KEY, before search logs had their own secret.
 * It waits until CAPELL_SEARCH_LOG_HASH_SECRET is set, so an upgrade without it re-plans the
 * step next time. A later rotation of that secret is handled by `search:rehash-logs`.
 */
final class RehashSearchLogsStep extends AbstractUpgradeStep
{
    #[Override]
    public function id(): string
    {
        return 'capell-search.rehash-search-logs';
    }

    #[Override]
    public function label(): string
    {
        return __('capell-search::generic.upgrade_rehash_search_logs');
    }

    #[Override]
    public function package(): string
    {
        return 'capell-app/search';
    }

    #[Override]
    public function shouldRun(UpgradeContext $context): bool
    {
        return RehashSearchLogsAction::make()->hasStaleRows();
    }

    #[Override]
    public function run(UpgradeContext $context): bool
    {
        if (! $context->dryRun) {
            RehashSearchLogsAction::run();
        }

        return true;
    }
}
