<?php

declare(strict_types=1);

namespace Capell\Search\Console\Commands;

use Capell\Search\Actions\FlushScoutSearchSourcesAction;
use Illuminate\Console\Command;

final class FlushSearchCommand extends Command
{
    protected $signature = 'search:flush {--source= : Only flush one configured searchable source key}';

    protected $description = 'Flush configured Scout-backed search sources from their search indexes.';

    public function handle(): int
    {
        $flushedSources = FlushScoutSearchSourcesAction::run($this->sourceOption());

        if ($flushedSources === []) {
            $this->warn(__('capell-search::actions.no_flushable_sources'));

            return self::FAILURE;
        }

        $this->info(__('capell-search::actions.flushed_sources', [
            'count' => count($flushedSources),
            'sources' => implode(', ', $flushedSources),
        ]));

        return self::SUCCESS;
    }

    private function sourceOption(): ?string
    {
        $source = $this->option('source');

        return is_string($source) && trim($source) !== '' ? trim($source) : null;
    }
}
