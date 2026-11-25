<?php

declare(strict_types=1);

namespace Capell\Search\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\TableName;
use Capell\Search\Actions\ProbeScoutIndexHealthAction;
use Capell\Search\Actions\RehashSearchLogsAction;
use Capell\Search\Actions\ResolveSearchDriverAction;
use Capell\Search\Actions\ResolveSearchSettingAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Models\SearchLog;
use Capell\Search\Support\Hashing\SearchLogHashSecret;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SearchHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTableCheck(),
            $check->modelRegistrationCheck(),
            $check->searchDriverCheck(),
            $check->searchLogWriteCheck(),
            $check->loggingConfigurationCheck(),
            $check->logHashSecretCheck(),
            $check->scoutIndexHealthCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function storageTableCheck(): DoctorCheckResultData
    {
        $tableExists = $this->hasSearchLogTable();
        $tableName = $this->searchLogTableName();

        return new DoctorCheckResultData(
            label: 'Search: search log database table present',
            passed: $tableExists,
            message: $tableExists
                ? sprintf('The %s table is present for query analytics and click tracking.', $tableName)
                : sprintf('The %s table is missing.', $tableName),
            remediation: $tableExists
                ? null
                : 'Run the Capell migrations to create the search log table.',
            id: 'legacy.search.log.storage.table',
        );
    }

    public function modelRegistrationCheck(): DoctorCheckResultData
    {
        $modelRegistered = $this->hasSearchLogModelRegistration();

        return new DoctorCheckResultData(
            label: 'Search: search log records registered',
            passed: $modelRegistered,
            message: $modelRegistered
                ? 'The SearchLog model is registered with Capell Core.'
                : 'The SearchLog model is not registered with Capell Core.',
            remediation: $modelRegistered
                ? null
                : 'Ensure SearchServiceProvider registers SearchLog through CapellCore::registerModels().',
            id: 'legacy.searchlog.model.registration',
        );
    }

    public function searchDriverCheck(): DoctorCheckResultData
    {
        try {
            $driver = resolve(Search::class);
            $driverResolves = $driver instanceof Search;
        } catch (Throwable) {
            $driverResolves = false;
        }

        return new DoctorCheckResultData(
            label: 'Search: search service available',
            passed: $driverResolves,
            message: $driverResolves
                ? sprintf('The configured %s implementation resolves successfully.', Search::class)
                : sprintf('The configured %s implementation could not be resolved.', Search::class),
            remediation: $driverResolves
                ? null
                : 'Check capell-search.driver and any configured database, Site Discovery, or Scout dependencies.',
            id: 'legacy.search.driver.resolution',
        );
    }

    public function searchLogWriteCheck(): DoctorCheckResultData
    {
        $tableName = $this->searchLogTableName();

        if (! $this->hasSearchLogTable()) {
            return new DoctorCheckResultData(
                label: 'Search: search logs writable',
                passed: false,
                message: sprintf('The %s table is missing, so writeability could not be checked.', $tableName),
                remediation: 'Run the Capell migrations to create the search log table.',
                id: 'legacy.search.log.write.probe',
            );
        }

        try {
            // Through the model, so the probe is written the way real logs are: encrypted.
            $probe = SearchLog::query()->create([
                'query' => '__capell_health_probe__',
                'normalized_query' => hash('sha256', '__capell_health_probe__'),
                'results_count' => 0,
                'searched_at' => now(),
            ]);

            DB::table($tableName)->where('id', $probe->getKey())->delete();

            $writeable = true;
        } catch (Throwable) {
            $writeable = false;
        }

        return new DoctorCheckResultData(
            label: 'Search: search logs writable',
            passed: $writeable,
            message: $writeable
                ? sprintf('The %s table accepts query-log writes.', $tableName)
                : sprintf('The %s table rejected a query-log write probe.', $tableName),
            remediation: $writeable
                ? null
                : 'Verify the search log migration columns and database write permissions.',
            id: 'legacy.search.log.write.probe',
        );
    }

    public function loggingConfigurationCheck(): DoctorCheckResultData
    {
        $minimumQueryLength = config('capell-search.minimum_query_length', 2);
        $retentionDays = config('capell-search.logs.retention_days', 180);
        $configurationValid = $this->hasValidLoggingConfiguration();

        return new DoctorCheckResultData(
            label: 'Search: search logging configured',
            passed: $configurationValid,
            message: $configurationValid
                ? 'Search query logging has a valid minimum query length and retention window.'
                : sprintf('Invalid search logging configuration: minimum_query_length=%s, retention_days=%s.', $minimumQueryLength, $retentionDays),
            remediation: $configurationValid
                ? null
                : 'Set capell-search.minimum_query_length and capell-search.logs.retention_days to positive integers.',
            id: 'legacy.search.logging.configuration',
        );
    }

    public function logHashSecretCheck(): DoctorCheckResultData
    {
        $loggingEnabled = (bool) ResolveSearchSettingAction::run('record_search_logs', 'capell-search.record_search_logs', true);
        $configured = SearchLogHashSecret::configured();
        $staleRows = $configured ? RehashSearchLogsAction::make()->staleRowCount() : 0;

        if ($configured && $staleRows > 0) {
            return new DoctorCheckResultData(
                label: 'Search: search log hash secret',
                passed: false,
                message: sprintf('%d search log records are still keyed by an earlier hash secret, so they no longer group with new searches.', $staleRows),
                remediation: 'Run php artisan search:rehash-logs to re-key them from their encrypted values.',
                id: 'search.log.hash.secret',
            );
        }

        return new DoctorCheckResultData(
            label: 'Search: search log hash secret',
            passed: $configured || ! $loggingEnabled,
            message: match (true) {
                $configured => 'Search log hashes use their own secret.',
                ! $loggingEnabled => 'Search logging is off, so no hash secret is needed.',
                default => sprintf('Search logging is on but %s is not set, so no searches are being recorded. Search logs never fall back to APP_KEY.', SearchLogHashSecret::ENVIRONMENT_KEY),
            },
            remediation: $configured || ! $loggingEnabled
                ? null
                : sprintf('Set %s to a long random value (for example the output of openssl rand -base64 48), clear the config cache, then run php artisan search:rehash-logs.', SearchLogHashSecret::ENVIRONMENT_KEY),
            id: 'search.log.hash.secret',
        );
    }

    public function scoutIndexHealthCheck(): DoctorCheckResultData
    {
        if (ResolveSearchDriverAction::run() !== 'scout' || config('scout.driver') !== 'typesense') {
            return new DoctorCheckResultData(
                label: 'Search: configured index checks',
                passed: true,
                message: 'Scout index probes were skipped because the active search stack is not Scout with Typesense.',
                id: 'legacy.scout.index.health',
            );
        }

        $configuredProbes = config('capell-search.health.scout_indexes', []);

        if (! is_array($configuredProbes) || $configuredProbes === []) {
            return new DoctorCheckResultData(
                label: 'Search: configured index checks',
                passed: true,
                message: 'No Scout index health probes are configured.',
                id: 'legacy.scout.index.health',
            );
        }

        $failures = [];

        foreach ($configuredProbes as $key => $probe) {
            $label = is_string($key) ? $key : sprintf('probe %d', $key + 1);
            $normalizedProbe = $this->normalizeScoutIndexProbe($probe);

            if ($normalizedProbe === null) {
                $failures[] = sprintf('%s has invalid configuration', $label);

                continue;
            }

            try {
                $result = resolve(ProbeScoutIndexHealthAction::class)->handle($normalizedProbe);

                if ($result['database_count'] !== $result['index_count']) {
                    $failures[] = sprintf(
                        '%s document count differs (database: %d, index: %d)',
                        $label,
                        $result['database_count'],
                        $result['index_count'],
                    );
                }

                if (! $result['sample_matched']) {
                    $failures[] = sprintf('%s sample query did not return the expected model', $label);
                }
            } catch (Throwable $exception) {
                $failures[] = sprintf('%s index could not be queried: %s', $label, $exception->getMessage());
            }
        }

        return new DoctorCheckResultData(
            label: 'Search: configured index checks',
            passed: $failures === [],
            message: $failures === []
                ? sprintf('%d configured Scout index probe(s) passed.', count($configuredProbes))
                : implode('; ', $failures) . '.',
            remediation: $failures === []
                ? null
                : 'Create or rebuild the affected Typesense index, then confirm its searchable model scope and sample query configuration.',
            id: 'legacy.scout.index.health',
        );
    }

    public function hasSearchLogTable(): bool
    {
        return Schema::hasTable($this->searchLogTableName());
    }

    public function hasSearchLogModelRegistration(): bool
    {
        return in_array(SearchLog::class, CapellCore::getModels(), true);
    }

    public function hasValidLoggingConfiguration(): bool
    {
        $minimumQueryLength = config('capell-search.minimum_query_length', 2);
        $retentionDays = config('capell-search.logs.retention_days', 180);

        return is_numeric($minimumQueryLength)
            && (int) $minimumQueryLength > 0
            && is_numeric($retentionDays)
            && (int) $retentionDays > 0;
    }

    /**
     * @return array{model: class-string<Model>, query: string, expected_model: array{column: string, value: int|string}, index?: string|null, database_count_method?: string}|null
     */
    private function normalizeScoutIndexProbe(mixed $probe): ?array
    {
        if (! is_array($probe)) {
            return null;
        }

        $modelClass = $probe['model'] ?? null;
        $query = $probe['query'] ?? null;

        $expectedModel = $probe['expected_model'] ?? null;
        $expectedColumn = is_array($expectedModel) ? ($expectedModel['column'] ?? null) : null;
        $expectedValue = is_array($expectedModel) ? ($expectedModel['value'] ?? null) : null;
        $index = $probe['index'] ?? null;
        $databaseCountMethod = $probe['database_count_method'] ?? null;

        if (! is_string($modelClass)
            || ! is_a($modelClass, Model::class, true)
            || ! is_string($query)
            || trim($query) === ''
            || ! is_string($expectedColumn)
            || $expectedColumn === ''
            || (! is_int($expectedValue) && ! is_string($expectedValue))
            || ($index !== null && ! is_string($index))
            || ($databaseCountMethod !== null && ! is_string($databaseCountMethod))) {
            return null;
        }

        $normalized = [
            'model' => $modelClass,
            'query' => $query,
            'expected_model' => [
                'column' => $expectedColumn,
                'value' => $expectedValue,
            ],
        ];

        if ($index !== null) {
            $normalized['index'] = $index;
        }

        if ($databaseCountMethod !== null) {
            $normalized['database_count_method'] = $databaseCountMethod;
        }

        return $normalized;
    }

    private function searchLogTableName(): string
    {
        return TableName::resolve(Config::string('capell-search.logs.table_name', 'search_logs'));
    }
}
