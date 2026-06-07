<?php

declare(strict_types=1);

namespace Capell\Search\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\Search\Contracts\Search;
use Capell\Search\Models\SearchLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SearchHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
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
            label: 'Search log storage table',
            passed: $tableExists,
            message: $tableExists
                ? sprintf('The %s table is present for query analytics and click tracking.', $tableName)
                : sprintf('The %s table is missing.', $tableName),
            remediation: $tableExists
                ? null
                : 'Run the Capell migrations to create the search log table.',
        );
    }

    public function modelRegistrationCheck(): DoctorCheckResultData
    {
        $modelRegistered = $this->hasSearchLogModelRegistration();

        return new DoctorCheckResultData(
            label: 'SearchLog model registration',
            passed: $modelRegistered,
            message: $modelRegistered
                ? 'The SearchLog model is registered with Capell Core.'
                : 'The SearchLog model is not registered with Capell Core.',
            remediation: $modelRegistered
                ? null
                : 'Ensure SearchServiceProvider registers SearchLog through CapellCore::registerModels().',
        );
    }

    public function searchDriverCheck(): DoctorCheckResultData
    {
        try {
            $driver = app(Search::class);
            $driverResolves = $driver instanceof Search;
        } catch (Throwable) {
            $driverResolves = false;
        }

        return new DoctorCheckResultData(
            label: 'Search driver resolution',
            passed: $driverResolves,
            message: $driverResolves
                ? sprintf('The configured %s implementation resolves successfully.', Search::class)
                : sprintf('The configured %s implementation could not be resolved.', Search::class),
            remediation: $driverResolves
                ? null
                : 'Check capell-search.driver and any configured database, Site Discovery, or Scout dependencies.',
        );
    }

    public function searchLogWriteCheck(): DoctorCheckResultData
    {
        $tableName = $this->searchLogTableName();

        if (! $this->hasSearchLogTable()) {
            return new DoctorCheckResultData(
                label: 'Search log write probe',
                passed: false,
                message: sprintf('The %s table is missing, so writeability could not be checked.', $tableName),
                remediation: 'Run the Capell migrations to create the search log table.',
            );
        }

        try {
            $recordId = DB::table($tableName)->insertGetId([
                'query' => '__capell_health_probe__',
                'normalized_query' => '__capell_health_probe__',
                'results_count' => 0,
                'searched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table($tableName)->where('id', $recordId)->delete();

            $writeable = true;
        } catch (Throwable) {
            $writeable = false;
        }

        return new DoctorCheckResultData(
            label: 'Search log write probe',
            passed: $writeable,
            message: $writeable
                ? sprintf('The %s table accepts query-log writes.', $tableName)
                : sprintf('The %s table rejected a query-log write probe.', $tableName),
            remediation: $writeable
                ? null
                : 'Verify the search log migration columns and database write permissions.',
        );
    }

    public function loggingConfigurationCheck(): DoctorCheckResultData
    {
        $minimumQueryLength = config('capell-search.minimum_query_length', 2);
        $retentionDays = config('capell-search.logs.retention_days', 180);
        $configurationValid = $this->hasValidLoggingConfiguration();

        return new DoctorCheckResultData(
            label: 'Search logging configuration',
            passed: $configurationValid,
            message: $configurationValid
                ? 'Search query logging has a valid minimum query length and retention window.'
                : sprintf('Invalid search logging configuration: minimum_query_length=%s, retention_days=%s.', $minimumQueryLength, $retentionDays),
            remediation: $configurationValid
                ? null
                : 'Set capell-search.minimum_query_length and capell-search.logs.retention_days to positive integers.',
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

    private function searchLogTableName(): string
    {
        $tableName = config('capell-search.logs.table_name', 'search_logs');

        return is_string($tableName) && $tableName !== '' ? $tableName : 'search_logs';
    }
}
