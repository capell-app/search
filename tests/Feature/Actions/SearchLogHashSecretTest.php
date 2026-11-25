<?php

declare(strict_types=1);

use Capell\Core\Data\UpgradeContext;
use Capell\Search\Actions\CreateSearchVisitorIdentityAction;
use Capell\Search\Actions\EncryptLegacySearchLogPiiAction;
use Capell\Search\Actions\GenerateSearchClickTokenAction;
use Capell\Search\Actions\RecordSearchAction;
use Capell\Search\Actions\RecordSearchResultClickAction;
use Capell\Search\Actions\RehashSearchLogsAction;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Health\SearchHealthCheck;
use Capell\Search\Models\SearchLog;
use Capell\Search\Providers\SearchServiceProvider;
use Capell\Search\Support\Hashing\SearchLogHashSecret;
use Capell\Search\Upgrade\RehashSearchLogsStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPackageTools\Package;

beforeEach(function (): void {
    config()->set('capell-search.record_search_logs', true);
    config()->set('capell-search.minimum_query_length', 2);
    config()->set('capell-search.visitor_hash_secret', null);
});

it('records nothing and hashes nothing with APP_KEY when the search log hash secret is missing', function (): void {
    config()->set('capell-search.logs.hash_secret', null);

    $log = RecordSearchAction::run(new SearchRequestData(query: 'gift shop'), 3);

    expect($log)->toBeNull()
        ->and(DB::table('search_logs')->count())->toBe(0)
        ->and(GenerateSearchClickTokenAction::run(new SearchRequestData(query: 'gift shop'), 'https://example.test/gifts'))->toBeNull()
        ->and(fn () => SearchLogHashSecret::current())->toThrow(RuntimeException::class, 'CAPELL_SEARCH_LOG_HASH_SECRET');
});

it('keys visitor hashes with the search log secret and never with APP_KEY', function (): void {
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Capell Test Browser']);

    config()->set('capell-search.logs.hash_secret', null);
    expect(CreateSearchVisitorIdentityAction::run($request, 1)->ipHash)->toBeNull();

    config()->set('capell-search.logs.hash_secret', 'search-secret-a');
    $withSecretA = CreateSearchVisitorIdentityAction::run($request, 1)->ipHash;
    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

    expect($withSecretA)->toBeString()
        ->and(CreateSearchVisitorIdentityAction::run($request, 1)->ipHash)->toBe($withSecretA);
});

it('keys new search logs with the configured secret and records which secret wrote them', function (): void {
    config()->set('capell-search.logs.hash_secret', 'search-secret-a');

    $log = RecordSearchAction::run(new SearchRequestData(query: 'Gift Shop'), 3);
    $raw = DB::table('search_logs')->where('id', $log?->getKey())->sole();

    expect($raw->normalized_query_hash)->toBe(hash_hmac('sha256', 'gift shop', 'search-secret-a'))
        ->and($raw->normalized_query)->toBe($raw->normalized_query_hash)
        ->and($raw->hash_key_id)->toBe(SearchLogHashSecret::keyId('search-secret-a'))
        ->and($raw->hash_key_id)->not->toContain('search-secret-a')
        ->and(Crypt::decryptString($raw->query))->toBe('gift shop');
});

it('re-keys retained hashes from their encrypted values when the secret changes, once', function (): void {
    config()->set('capell-search.logs.hash_secret', 'search-secret-a');
    $log = RecordSearchAction::run(new SearchRequestData(query: 'gift shop'), 3);
    throw_unless($log instanceof SearchLog, RuntimeException::class, 'The search was not recorded.');
    RecordSearchResultClickAction::run($log, 'https://example.test/gifts');
    $appKey = 'base64:' . base64_encode(str_repeat('k', 32));
    config()->set('app.key', $appKey);
    // A row written before the key id existed, keyed by APP_KEY.
    $appKeyRow = DB::table('search_logs')->insertGetId([
        'query' => Crypt::encryptString('old query'),
        'normalized_query' => hash_hmac('sha256', 'old query', $appKey),
        'normalized_query_hash' => hash_hmac('sha256', 'old query', $appKey),
        'results_count' => 1,
        'searched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    config()->set('capell-search.logs.hash_secret', 'search-secret-b');
    expect(resolve(RehashSearchLogsStep::class)->shouldRun(upgradeContextForSearchHashTest()))->toBeTrue()
        ->and(RehashSearchLogsAction::run())->toBe(['rehashed' => 2, 'undecryptable' => 0]);

    $rekeyed = DB::table('search_logs')->where('id', $log->getKey())->sole();
    $old = DB::table('search_logs')->where('id', $appKeyRow)->sole();

    expect($rekeyed->normalized_query_hash)->toBe(hash_hmac('sha256', 'gift shop', 'search-secret-b'))
        ->and($rekeyed->normalized_query)->toBe($rekeyed->normalized_query_hash)
        ->and($rekeyed->clicked_result_hash)->toBe(hash_hmac('sha256', '/gifts', 'search-secret-b'))
        ->and($rekeyed->hash_key_id)->toBe(SearchLogHashSecret::keyId('search-secret-b'))
        ->and($old->normalized_query_hash)->toBe(hash_hmac('sha256', 'old query', 'search-secret-b'))
        ->and(RehashSearchLogsAction::run())->toBe(['rehashed' => 0, 'undecryptable' => 0])
        ->and(resolve(RehashSearchLogsStep::class)->shouldRun(upgradeContextForSearchHashTest()))->toBeFalse();
});

it('leaves rows it cannot decrypt untouched and makes the command fail', function (): void {
    config()->set('capell-search.logs.hash_secret', 'search-secret-a');
    $id = DB::table('search_logs')->insertGetId([
        'query' => 'not-an-encrypted-payload',
        'normalized_query' => 'stale-hash',
        'normalized_query_hash' => 'stale-hash',
        'results_count' => 1,
        'searched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Artisan::call('search:rehash-logs'))->toBe(1)
        ->and(DB::table('search_logs')->where('id', $id)->value('normalized_query_hash'))->toBe('stale-hash');
});

it('refuses to rehash without a secret and reports the missing secret in health', function (): void {
    config()->set('capell-search.logs.hash_secret', null);

    $check = (new SearchHealthCheck)->logHashSecretCheck();

    expect(Artisan::call('search:rehash-logs'))->toBe(1)
        ->and($check->passed)->toBeFalse()
        ->and($check->remediation)->toContain('CAPELL_SEARCH_LOG_HASH_SECRET');

    config()->set('capell-search.record_search_logs', false);
    expect((new SearchHealthCheck)->logHashSecretCheck()->passed)->toBeTrue();
});

it('redacts and encrypts plain-text rows written around the model, keyed by the configured secret', function (): void {
    config()->set('capell-search.logs.hash_secret', 'search-secret-a');
    $id = DB::table('search_logs')->insertGetId([
        'query' => 'reach me at ada@example.com',
        'normalized_query' => 'reach me at ada@example.com',
        'results_count' => 0,
        'searched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    EncryptLegacySearchLogPiiAction::run();
    $raw = DB::table('search_logs')->where('id', $id)->sole();

    expect(Crypt::decryptString($raw->query))->toBe('reach me at [email]')
        ->and($raw->normalized_query_hash)->toBe(hash_hmac('sha256', 'reach me at [email]', 'search-secret-a'))
        ->and($raw->hash_key_id)->toBe(SearchLogHashSecret::keyId('search-secret-a'))
        ->and(json_encode($raw))->not->toContain('ada@example.com');
});

it('writes the health probe encrypted rather than as plain text', function (): void {
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->bindings;
    });

    expect((new SearchHealthCheck)->searchLogWriteCheck()->passed)->toBeTrue()
        ->and(collect($queries)->flatten()->contains('__capell_health_probe__'))->toBeFalse();
});

function upgradeContextForSearchHashTest(): UpgradeContext
{
    return new UpgradeContext(composerVersions: [], ledgerVersions: [], appliedStepIds: [], dryRun: false);
}

it('registers every migration it ships, so installs gain the hash key id column', function (): void {
    $package = new Package;
    (new SearchServiceProvider(app()))->configurePackage($package);
    $files = collect(glob(dirname(__DIR__, 3) . '/database/migrations/*.php') ?: [])
        ->map(static fn (string $path): string => basename($path, '.php'))
        ->sort()
        ->values()
        ->all();

    expect(collect($package->migrationFileNames)->sort()->values()->all())->toBe($files);
});

it('re-keys a legacy row with mixed case and spacing so it still groups with the same search typed today', function (): void {
    config()->set('capell-search.logs.hash_secret', 'search-secret-a');
    $id = DB::table('search_logs')->insertGetId([
        'query' => 'Opening   Hours',
        'normalized_query' => 'opening hours',
        'results_count' => 0,
        'searched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    EncryptLegacySearchLogPiiAction::run();

    config()->set('capell-search.logs.hash_secret', 'search-secret-b');
    RehashSearchLogsAction::run();

    $today = RecordSearchAction::run(new SearchRequestData(query: 'opening hours'), 3);

    expect($today)->toBeInstanceOf(SearchLog::class)
        ->and(DB::table('search_logs')->where('id', $id)->value('normalized_query_hash'))
        ->toBe(DB::table('search_logs')->where('id', $today?->getKey())->value('normalized_query_hash'));
});
