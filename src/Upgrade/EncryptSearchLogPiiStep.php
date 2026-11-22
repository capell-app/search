<?php

declare(strict_types=1);

namespace Capell\Search\Upgrade;

use Capell\Core\Data\UpgradeContext;
use Capell\Core\Support\Upgrade\AbstractUpgradeStep;
use Capell\Search\Actions\EncryptLegacySearchLogPiiAction;
use Illuminate\Support\Facades\Schema;
use Override;

/**
 * Encrypts search logs written before logs stored their query encrypted. It runs
 * under Core's upgrade lock, straight after the migration that adds the hash
 * columns, and only while unconverted rows remain.
 */
final class EncryptSearchLogPiiStep extends AbstractUpgradeStep
{
    public const string TAG = 'capell.upgrade-steps';

    #[Override]
    public function id(): string
    {
        return 'capell-search.encrypt-search-log-pii';
    }

    #[Override]
    public function label(): string
    {
        return __('capell-search::generic.upgrade_encrypt_search_log_pii');
    }

    #[Override]
    public function package(): string
    {
        return 'capell-app/search';
    }

    #[Override]
    public function shouldRun(UpgradeContext $context): bool
    {
        $table = EncryptLegacySearchLogPiiAction::table();

        return Schema::hasTable($table)
            && Schema::hasColumns($table, ['normalized_query_hash', 'clicked_result_hash'])
            && EncryptLegacySearchLogPiiAction::make()->hasLegacyRows();
    }

    #[Override]
    public function run(UpgradeContext $context): bool
    {
        if (! $context->dryRun) {
            EncryptLegacySearchLogPiiAction::run();
        }

        return true;
    }
}
