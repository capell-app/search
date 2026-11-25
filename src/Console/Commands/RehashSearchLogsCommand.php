<?php

declare(strict_types=1);

namespace Capell\Search\Console\Commands;

use Capell\Search\Actions\EncryptLegacySearchLogPiiAction;
use Capell\Search\Actions\RehashSearchLogsAction;
use Capell\Search\Support\Hashing\SearchLogHashSecret;
use Illuminate\Console\Command;

final class RehashSearchLogsCommand extends Command
{
    protected $signature = 'search:rehash-logs';

    protected $description = 'Re-key retained search log hashes with the current search log hash secret.';

    public function handle(): int
    {
        if (! SearchLogHashSecret::configured()) {
            $this->error(__('capell-search::actions.rehash_logs_missing_secret', ['key' => SearchLogHashSecret::ENVIRONMENT_KEY]));

            return self::FAILURE;
        }

        $encrypted = EncryptLegacySearchLogPiiAction::make()->hasLegacyRows() ? EncryptLegacySearchLogPiiAction::run() : 0;
        $result = RehashSearchLogsAction::run();

        $this->info(__('capell-search::actions.rehashed_logs', [
            'count' => $result['rehashed'] + $encrypted,
        ]));

        if ($result['undecryptable'] > 0) {
            $this->warn(__('capell-search::actions.rehash_logs_undecryptable', ['count' => $result['undecryptable']]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
