<?php

declare(strict_types=1);

use Capell\Core\Data\UpgradeContext;
use Capell\Search\Actions\EncryptLegacySearchLogPiiAction;
use Capell\Search\Actions\HashSearchRetentionValueAction;
use Capell\Search\Models\SearchLog;
use Capell\Search\Upgrade\EncryptSearchLogPiiStep;
use Illuminate\Support\Facades\DB;

function legacySearchLog(string $query, ?string $clickedUrl = null): int
{
    return (int) DB::table(EncryptLegacySearchLogPiiAction::table())->insertGetId([
        'query' => $query,
        'normalized_query' => mb_strtolower($query),
        'normalized_query_hash' => null,
        'clicked_result_url' => $clickedUrl,
        'clicked_result_hash' => null,
        'results_count' => 3,
        'ip_hash' => hash('sha256', '203.0.113.10'),
        'user_agent_hash' => hash('sha256', 'Capell Test Browser'),
        'searched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function upgradeContext(): UpgradeContext
{
    return new UpgradeContext(composerVersions: [], ledgerVersions: [], appliedStepIds: []);
}

it('encrypts plaintext search history and hashes the normalised query', function (): void {
    $legacyId = legacySearchLog('Opening Hours', '/visit/opening-hours');
    $step = resolve(EncryptSearchLogPiiStep::class);

    expect($step->shouldRun(upgradeContext()))->toBeTrue()
        ->and($step->run(upgradeContext()))->toBeTrue();

    $raw = DB::table(EncryptLegacySearchLogPiiAction::table())->where('id', $legacyId)->sole();
    $log = SearchLog::query()->whereKey($legacyId)->sole();

    expect($raw->query)->not->toBe('Opening Hours')
        ->and($log->query)->toBe('Opening Hours')
        ->and($log->clicked_result_url)->toBe('/visit/opening-hours')
        ->and($raw->normalized_query)->toBe(HashSearchRetentionValueAction::run('opening hours'))
        ->and($raw->normalized_query_hash)->toBe($raw->normalized_query)
        ->and($raw->clicked_result_hash)->toBe(HashSearchRetentionValueAction::run('/visit/opening-hours'))
        ->and($step->shouldRun(upgradeContext()))->toBeFalse();
});

it('leaves converted and newly recorded rows unchanged when it runs again', function (): void {
    legacySearchLog('Parking');
    $recorded = SearchLog::factory()->create(['query' => 'Tickets']);
    EncryptLegacySearchLogPiiAction::run();
    $before = DB::table(EncryptLegacySearchLogPiiAction::table())->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();

    expect(EncryptLegacySearchLogPiiAction::run())->toBe(0)
        ->and(DB::table(EncryptLegacySearchLogPiiAction::table())->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all())->toBe($before)
        ->and(SearchLog::query()->whereKey($recorded->id)->sole()->query)->toBe('Tickets');
});

it('converts every legacy row in one run when there are more rows than one chunk', function (): void {
    foreach (range(1, 7) as $number) {
        legacySearchLog('Query ' . $number);
    }

    expect(EncryptLegacySearchLogPiiAction::run(chunkSize: 2))->toBe(7)
        ->and(EncryptLegacySearchLogPiiAction::make()->hasLegacyRows())->toBeFalse();
});

it('changes nothing on a dry run', function (): void {
    $legacyId = legacySearchLog('Gift shop');

    resolve(EncryptSearchLogPiiStep::class)->run(new UpgradeContext(composerVersions: [], ledgerVersions: [], appliedStepIds: [], dryRun: true));

    expect(DB::table(EncryptLegacySearchLogPiiAction::table())->where('id', $legacyId)->value('query'))->toBe('Gift shop');
});

it('is registered with the Core upgrade runner', function (): void {
    $steps = array_map(static fn (object $step): string => $step::class, iterator_to_array(app()->tagged('capell.upgrade-steps')));

    expect($steps)->toContain(EncryptSearchLogPiiStep::class);
});
