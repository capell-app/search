<?php

declare(strict_types=1);

use Capell\Search\Data\SearchResultData;
use Capell\Search\Drivers\DatabaseSearch;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;

// Bootstrap an in-memory SQLite database once for all tests in this file.
beforeAll(function (): void {
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('pages', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('title');
        $table->text('excerpt')->nullable();
        $table->text('body')->nullable();
        $table->string('slug');
        $table->string('type')->default('page');
        $table->unsignedInteger('site_id')->nullable();
        $table->unsignedInteger('language_id')->nullable();
        $table->string('status')->default('published');
    });

    Capsule::table('pages')->insert([
        ['title' => 'Laravel Tutorial', 'excerpt' => 'Learn Laravel today', 'body' => null, 'slug' => 'laravel-tutorial', 'type' => 'post', 'site_id' => 1, 'language_id' => 1, 'status' => 'published'],
        ['title' => 'About Us', 'excerpt' => 'Company info', 'body' => 'We are a company', 'slug' => 'about', 'type' => 'page', 'site_id' => 1, 'language_id' => 1, 'status' => 'published'],
        ['title' => 'Contact', 'excerpt' => null, 'body' => 'Reach out anytime', 'slug' => 'contact', 'type' => 'page', 'site_id' => 1, 'language_id' => 1, 'status' => 'published'],
        ['title' => '50% Discount', 'excerpt' => 'Literal percent offer', 'body' => null, 'slug' => 'percent', 'type' => 'page', 'site_id' => 1, 'language_id' => 1, 'status' => 'published'],
        ['title' => '500 Discount', 'excerpt' => 'Numeric offer', 'body' => null, 'slug' => 'numeric', 'type' => 'page', 'site_id' => 1, 'language_id' => 1, 'status' => 'published'],
        ['title' => 'Laravel French', 'excerpt' => 'Learn Laravel in French', 'body' => null, 'slug' => 'laravel-french', 'type' => 'post', 'site_id' => 1, 'language_id' => 2, 'status' => 'published'],
        ['title' => 'Laravel Draft', 'excerpt' => 'Unpublished Laravel page', 'body' => null, 'slug' => 'laravel-draft', 'type' => 'post', 'site_id' => 1, 'language_id' => 1, 'status' => 'draft'],
        ['title' => 'Laravel Other Site', 'excerpt' => 'Other site Laravel page', 'body' => null, 'slug' => 'laravel-other-site', 'type' => 'post', 'site_id' => 2, 'language_id' => 1, 'status' => 'published'],
    ]);

    Capsule::schema()->create('search_pages_without_status', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('title');
        $table->text('excerpt')->nullable();
        $table->text('body')->nullable();
        $table->string('slug');
        $table->string('type')->default('page');
    });

    Capsule::table('search_pages_without_status')->insert([
        ['title' => 'Laravel Public Article', 'excerpt' => 'Searchable public content', 'body' => null, 'slug' => 'laravel-public-article', 'type' => 'page'],
        ['title' => 'Laravel Private Article', 'excerpt' => 'Searchable private content', 'body' => null, 'slug' => 'laravel-private-article', 'type' => 'page'],
    ]);
});

test('returns empty paginator for empty query', function (): void {
    $databaseConnection = Mockery::mock(ConnectionInterface::class);

    $search = new DatabaseSearch($databaseConnection);
    $results = $search->search('   ');

    expect($results->total())->toBe(0);
    expect($results->isEmpty())->toBeTrue();
});

test('search returns matching results from the database', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('Laravel');
    $firstResult = $results->items()[0] ?? null;

    expect($results->total())->toBe(3);
    expect($firstResult)->toBeInstanceOf(SearchResultData::class);
    expect($firstResult?->title)->toBe('Laravel Tutorial');
});

test('search result url is prefixed with a leading slash', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('Laravel');
    $firstResult = $results->items()[0] ?? null;

    expect($firstResult?->url)->toBe('/laravel-tutorial');
});

test('search falls back to body when excerpt is null', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('anytime');
    $firstResult = $results->items()[0] ?? null;

    expect($results->total())->toBe(1);
    expect($firstResult?->excerpt)->toContain('anytime');
});

test('search returns all matches when multiple rows satisfy query', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('ar');

    expect($results->total())->toBeGreaterThanOrEqual(2);
});

test('search paginates correctly', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $firstPage = $search->search('ar', perPage: 1, page: 1);
    $secondPage = $search->search('ar', perPage: 1, page: 2);

    expect($firstPage->items())->toHaveCount(1);
    expect($secondPage->items())->toHaveCount(1);
    expect(($firstPage->items()[0] ?? null)?->url)->not->toBe(($secondPage->items()[0] ?? null)?->url);
});

test('search requires a meaningful minimum query length', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('a');

    expect($results->total())->toBe(0);
    expect($results->isEmpty())->toBeTrue();
});

test('search escapes like wildcards', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('50%');
    $firstResult = $results->items()[0] ?? null;

    expect($results->total())->toBe(1);
    expect($firstResult?->title)->toBe('50% Discount');
});

test('search clamps pagination arguments', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('Laravel', perPage: 500, page: -10);

    expect($results->perPage())->toBe(100);
    expect($results->currentPage())->toBe(1);
});

test('search score is a float', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('Laravel');
    $firstResult = $results->items()[0] ?? null;

    expect($firstResult?->score)->toBeFloat();
});

test('search filters by site language and published status when columns are present', function (): void {
    $search = new DatabaseSearch(Capsule::connection());

    $results = $search->search('Laravel', siteId: 1, languageId: 1);

    expect($results->total())->toBe(1);
    expect(($results->items()[0] ?? null)?->url)->toBe('/laravel-tutorial');
});

test('search returns no public results when configured status guard column is missing', function (): void {
    $search = new DatabaseSearch(
        db: Capsule::connection(),
        table: 'search_pages_without_status',
    );

    $results = $search->search('Laravel');

    expect($results->total())->toBe(0);
    expect($results->isEmpty())->toBeTrue();
});

test('fulltext index compatibility allows covering indexes regardless of column order', function (): void {
    $search = new DatabaseSearch(Capsule::connection());
    $method = new ReflectionMethod(DatabaseSearch::class, 'hasCompatibleFullTextIndex');

    expect($method->invoke($search, [
        ['body', 'excerpt', 'title'],
    ], ['title', 'excerpt']))->toBeTrue();
});

test('fulltext index compatibility rejects indexes missing configured search columns', function (): void {
    $search = new DatabaseSearch(Capsule::connection());
    $method = new ReflectionMethod(DatabaseSearch::class, 'hasCompatibleFullTextIndex');

    expect($method->invoke($search, [
        ['title', 'excerpt'],
    ], ['title', 'excerpt', 'body']))->toBeFalse();
});

test('wraps matches in <mark> tags with escaping', function (): void {
    $databaseConnection = Mockery::mock(ConnectionInterface::class);
    $search = new DatabaseSearch($databaseConnection);

    $html = $search->highlight('<b>Laravel is great</b> for sites', 'Laravel');

    expect($html)
        ->toContain('<mark>Laravel</mark>')
        ->toContain('&lt;b&gt;');
});

test('highlight returns escaped text when query is empty', function (): void {
    $databaseConnection = Mockery::mock(ConnectionInterface::class);
    $search = new DatabaseSearch($databaseConnection);

    $html = $search->highlight('<script>alert(1)</script>', '');

    expect($html)->toContain('&lt;script&gt;');
    expect($html)->not->toContain('<mark>');
});
