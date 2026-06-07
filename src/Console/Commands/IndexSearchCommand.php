<?php

declare(strict_types=1);

namespace Capell\Search\Console\Commands;

use Capell\Search\Actions\IndexScoutSearchSourcesAction;
use Illuminate\Console\Command;

final class IndexSearchCommand extends Command
{
    protected $signature = 'search:index {--source= : Only index one configured searchable source key} {--chunk= : Scout import chunk size}';

    protected $description = 'Import configured Scout-backed search sources into their search indexes.';

    public function handle(): int
    {
        $chunk = $this->resolveChunkOption();

        if ($chunk === 0) {
            return self::FAILURE;
        }

        $indexedSources = IndexScoutSearchSourcesAction::run(
            sourceKey: $this->sourceOption(),
            chunk: $chunk,
        );

        if ($indexedSources === []) {
            $this->warn(__('capell-search::actions.no_indexable_sources'));

            return self::FAILURE;
        }

        $this->info(__('capell-search::actions.indexed_sources', [
            'count' => count($indexedSources),
            'sources' => implode(', ', $indexedSources),
        ]));

        return self::SUCCESS;
    }

    private function sourceOption(): ?string
    {
        $source = $this->option('source');

        return is_string($source) && trim($source) !== '' ? trim($source) : null;
    }

    private function resolveChunkOption(): ?int
    {
        $chunkOption = $this->option('chunk');

        if ($chunkOption === null || $chunkOption === '') {
            $configuredChunk = config('capell-search.scout.chunk');

            return is_numeric($configuredChunk) ? max(1, (int) $configuredChunk) : null;
        }

        if (! is_string($chunkOption) && ! is_int($chunkOption)) {
            $this->error(__('capell-search::actions.invalid_chunk'));

            return 0;
        }

        $chunk = (string) $chunkOption;

        if (! ctype_digit($chunk) || (int) $chunk < 1) {
            $this->error(__('capell-search::actions.invalid_chunk'));

            return 0;
        }

        return (int) $chunk;
    }
}
