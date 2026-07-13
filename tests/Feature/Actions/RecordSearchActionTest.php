<?php

declare(strict_types=1);

use Capell\Search\Actions\GenerateSearchClickTokenAction;
use Capell\Search\Actions\HashSearchRetentionValueAction;
use Capell\Search\Actions\RecordSearchAction;
use Capell\Search\Actions\RecordSearchResultClickAction;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchVisitorIdentityData;
use Capell\Search\Models\SearchLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('capell-search.logs.table_name', 'search_logs');
    config()->set('capell-search.minimum_query_length', 2);
    config()->set('capell-search.record_search_logs', true);
    config()->set('capell-search.hash_visitor_data', true);

    Schema::dropIfExists('search_logs');
    Schema::create('search_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->index();
        $table->foreignId('language_id')->nullable()->index();
        $table->string('query');
        $table->string('normalized_query')->index();
        $table->string('normalized_query_hash', 64)->nullable()->index();
        $table->unsignedInteger('results_count')->default(0);
        $table->string('clicked_result_url')->nullable();
        $table->string('clicked_result_hash', 64)->nullable()->index();
        $table->string('ip_hash', 64)->nullable();
        $table->string('user_agent_hash', 64)->nullable();
        $table->timestamp('searched_at')->index();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('search_logs');
});

test('logs valid searches with normalized query and hashed visitor data', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(
            query: '  Laravel   Search  ',
            siteId: 10,
            languageId: 20,
        ),
        7,
        searchVisitorIdentity(),
    );

    $log = searchLogResult($log);

    expect($log->site_id)->toBe(10);
    expect($log->language_id)->toBe(20);
    expect($log->query)->toBe('laravel search');
    expect($log->normalized_query)->toBe(HashSearchRetentionValueAction::run('laravel search'));
    expect($log->getAttribute('normalized_query_hash'))->toBe(HashSearchRetentionValueAction::run('laravel search'));
    expect($log->results_count)->toBe(7);
    expect($log->ip_hash)->toBe(searchVisitorIdentity()->ipHash)
        ->and($log->user_agent_hash)->toBe(searchVisitorIdentity()->userAgentHash);
    expect($log->searched_at)->toBeInstanceOf(CarbonImmutable::class);
    $rawQuery = DB::table('search_logs')->where('id', $log->getKey())->value('query');
    $rawNormalized = DB::table('search_logs')->where('id', $log->getKey())->value('normalized_query');
    expect($rawQuery)->toBeString()->not->toContain('laravel search')
        ->and($rawNormalized)->toBe(HashSearchRetentionValueAction::run('laravel search'))
        ->not->toContain('laravel search');
});

test('skips blank searches', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(query: '   '),
        0,
    );

    expect($log)->toBeNull();
    expect(SearchLog::query()->count())->toBe(0);
});

test('skips searches shorter than the minimum query length', function (): void {
    config()->set('capell-search.minimum_query_length', 3);

    $log = RecordSearchAction::run(
        new SearchRequestData(query: 'ab'),
        0,
    );

    expect($log)->toBeNull();
    expect(SearchLog::query()->count())->toBe(0);
});

test('respects disabled search logging', function (): void {
    config()->set('capell-search.record_search_logs', false);

    $log = RecordSearchAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        1,
    );

    expect($log)->toBeNull();
    expect(SearchLog::query()->count())->toBe(0);
});

test('omits visitor hashes when visitor hashing is disabled', function (): void {
    config()->set('capell-search.hash_visitor_data', false);

    $log = RecordSearchAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        1,
        searchVisitorIdentity(),
    );

    $log = searchLogResult($log);

    expect($log->ip_hash)->toBeNull();
    expect($log->user_agent_hash)->toBeNull();
});

test('records clicked result url on an existing search log', function (): void {
    $log = SearchLog::factory()->create([
        'clicked_result_url' => null,
    ]);

    $updatedLog = RecordSearchResultClickAction::run($log, '/search-result');
    $updatedLog = searchLogResult($updatedLog);

    expect($updatedLog->clicked_result_url)->toBe('/search-result');
    expect($log->refresh()->clicked_result_url)->toBe('/search-result');
    $rawPath = DB::table('search_logs')->where('id', $log->getKey())->value('clicked_result_url');
    $rawPathHash = DB::table('search_logs')->where('id', $log->getKey())->value('clicked_result_hash');
    expect($rawPath)->toBeString()->not->toContain('/search-result')
        ->and($rawPathHash)->toBe(HashSearchRetentionValueAction::run('/search-result'));
});

test('generates click tokens with normalized search context', function (): void {
    $token = GenerateSearchClickTokenAction::run(new SearchRequestData(
        query: 'Laravel Search',
        siteId: 10,
        languageId: 20,
    ), '/search-result');

    throw_unless(is_string($token), RuntimeException::class, 'Expected search click token.');

    $payload = searchClickTokenPayload($token);

    expect($payload)->toMatchArray([
        'query_hash' => HashSearchRetentionValueAction::run('laravel search'),
        'site_id' => 10,
        'language_id' => 20,
        'result_path' => '/search-result',
    ])
        ->and($payload['issued_at'])->toBeInt();
});

test('skips click tokens for queries too short to log', function (): void {
    config()->set('capell-search.minimum_query_length', 3);

    expect(GenerateSearchClickTokenAction::run(new SearchRequestData(query: 'ab'), '/result'))->toBeNull();
});

test('records click by token when visitor hashes change', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(
            query: 'Laravel Search',
            siteId: 10,
            languageId: 20,
        ),
        1,
        searchVisitorIdentity('original-ip-hash', 'original-user-agent-hash'),
    );
    $token = GenerateSearchClickTokenAction::run(new SearchRequestData(
        query: 'Laravel Search',
        siteId: 10,
        languageId: 20,
    ), '/search-result');

    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected search log.');
    throw_unless(is_string($token), RuntimeException::class, 'Expected search click token.');

    $request = Request::create('/search/click', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
        'REMOTE_ADDR' => '203.0.113.99',
        'HTTP_USER_AGENT' => 'Changed Browser',
    ]);
    $request->attributes->set('site', (object) ['id' => 10]);
    $request->attributes->set('language', (object) ['id' => 20]);

    $updatedLog = RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/search-result',
        token: $token,
    );

    expect($updatedLog?->is($log))->toBeTrue()
        ->and($log->refresh()->clicked_result_url)->toBe('/search-result');
});

test('binds a click token to one result and consumes the search log once', function (): void {
    $log = searchLogResult(RecordSearchAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        2,
    ));
    $firstToken = GenerateSearchClickTokenAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        '/first-result?tracking=private',
    );
    $secondToken = GenerateSearchClickTokenAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        '/second-result',
    );
    $request = Request::create('/search/click', Symfony\Component\HttpFoundation\Request::METHOD_POST);

    expect(RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/substituted-result',
        token: $firstToken,
    ))->toBeNull()
        ->and($log->refresh()->clicked_result_url)->toBeNull();

    RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/first-result?tracking=private',
        token: $firstToken,
    );

    expect($log->refresh()->clicked_result_url)->toBe('/first-result')
        ->and(RecordSearchResultClickAction::run(
            request: $request,
            query: 'Laravel Search',
            url: '/second-result',
            token: $secondToken,
        ))->toBeNull()
        ->and($log->refresh()->clicked_result_url)->toBe('/first-result');
});

/**
 * @return array{query_hash: string, site_id: int|null, language_id: int|null, result_path: string, issued_at: int}
 */
function searchClickTokenPayload(string $token): array
{
    $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        return [
            'query_hash' => '',
            'site_id' => null,
            'language_id' => null,
            'result_path' => '',
            'issued_at' => 0,
        ];
    }

    return [
        'query_hash' => is_string($payload['query_hash'] ?? null) ? $payload['query_hash'] : '',
        'site_id' => is_numeric($payload['site_id'] ?? null) ? (int) $payload['site_id'] : null,
        'language_id' => is_numeric($payload['language_id'] ?? null) ? (int) $payload['language_id'] : null,
        'result_path' => is_string($payload['result_path'] ?? null) ? $payload['result_path'] : '',
        'issued_at' => is_int($payload['issued_at'] ?? null) ? $payload['issued_at'] : 0,
    ];
}

function searchLogResult(?SearchLog $log): SearchLog
{
    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected search log.');

    return $log;
}

function searchVisitorIdentity(
    string $ipHash = 'ip-hash',
    string $userAgentHash = 'user-agent-hash',
): SearchVisitorIdentityData {
    return new SearchVisitorIdentityData($ipHash, $userAgentHash);
}

test('rejects click tracking when the token is invalid', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        1,
        searchVisitorIdentity(),
    );

    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected search log.');

    $request = Request::create('/search/click', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Capell Test Browser',
    ]);

    $updatedLog = RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/search-result',
        token: 'not-a-valid-token',
    );

    expect($updatedLog)->toBeNull()
        ->and($log->refresh()->clicked_result_url)->toBeNull();
});

test('rejects click tracking when the token is missing', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(query: 'Laravel Search'),
        1,
        searchVisitorIdentity(),
    );

    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected search log.');

    $request = Request::create('/search/click', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Capell Test Browser',
    ]);

    $updatedLog = RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/search-result',
    );

    expect($updatedLog)->toBeNull()
        ->and($log->refresh()->clicked_result_url)->toBeNull();
});

test('rejects click tracking when the token context does not match the request context', function (): void {
    $log = RecordSearchAction::run(
        new SearchRequestData(
            query: 'Laravel Search',
            siteId: 10,
            languageId: 20,
        ),
        1,
    );
    $token = GenerateSearchClickTokenAction::run(new SearchRequestData(
        query: 'Laravel Search',
        siteId: 10,
        languageId: 20,
    ), '/search-result');

    throw_unless($log instanceof SearchLog, RuntimeException::class, 'Expected search log.');
    throw_unless(is_string($token), RuntimeException::class, 'Expected search click token.');

    $request = Request::create('/search/click', Symfony\Component\HttpFoundation\Request::METHOD_POST);

    $updatedLog = RecordSearchResultClickAction::run(
        request: $request,
        query: 'Laravel Search',
        url: '/search-result',
        token: $token,
    );

    expect($updatedLog)->toBeNull()
        ->and($log->refresh()->clicked_result_url)->toBeNull();
});
