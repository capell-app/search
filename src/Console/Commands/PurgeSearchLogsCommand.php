<?php

declare(strict_types=1);

namespace Capell\Search\Console\Commands;

use Capell\Search\Actions\PurgeSearchLogsAction;
use Illuminate\Console\Command;

final class PurgeSearchLogsCommand extends Command
{
    protected $signature = 'search:purge {--days= : Override retention days}';

    protected $description = 'Delete old site search log records.';

    public function handle(): int
    {
        $retentionDays = $this->resolveRetentionDaysOption();

        if ($retentionDays === 0) {
            return self::FAILURE;
        }

        $deletedCount = PurgeSearchLogsAction::run($retentionDays);

        $this->info(__('capell-search::actions.purged_logs', [
            'count' => $deletedCount,
        ]));

        return self::SUCCESS;
    }

    private function resolveRetentionDaysOption(): ?int
    {
        $daysOption = $this->option('days');

        if ($daysOption === null || $daysOption === '') {
            return null;
        }

        if (! is_string($daysOption) && ! is_int($daysOption)) {
            $this->error('The --days option must be a positive integer.');

            return 0;
        }

        $daysValue = (string) $daysOption;

        if (! ctype_digit($daysValue) || (int) $daysValue < 1) {
            $this->error('The --days option must be a positive integer.');

            return 0;
        }

        return (int) $daysValue;
    }
}
