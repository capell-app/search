<?php

declare(strict_types=1);

use Capell\Core\Data\Database\SqlFragment;
use Capell\Search\Data\DatabaseSearchExpression;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Support\DatabaseFullText;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const SEARCH_COMPATIBILITY_TABLE = 'capell_search_compatibility_test';
const SEARCH_COMPATIBILITY_INDEX = 'capell_search_database_fulltext';

beforeEach(function (): void {
    Schema::dropIfExists(SEARCH_COMPATIBILITY_TABLE);
    Schema::create(SEARCH_COMPATIBILITY_TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->text('excerpt')->nullable();
        $table->text('body')->nullable();
        $table->string('slug');
        $table->string('type')->default('page');
        $table->string('status')->default('published');
    });
});

afterEach(function (): void {
    Schema::dropIfExists(SEARCH_COMPATIBILITY_TABLE);
});

it('uses the Search-owned full-text seam with a portable behavioral fallback', function (): void {
    $columns = ['title', 'excerpt', 'body'];
    $connection = DB::connection();
    $grammar = $connection->getQueryGrammar();
    $fullText = new DatabaseFullText;
    $index = $fullText->createIndex(
        $connection,
        SEARCH_COMPATIBILITY_TABLE,
        SEARCH_COMPATIBILITY_INDEX,
        $columns,
    );

    if ($index !== null) {
        DB::statement($index->sql, $index->bindings);
    }

    DB::table(SEARCH_COMPATIBILITY_TABLE)->insert([
        [
            'title' => 'Portable database search',
            'excerpt' => 'Works through the Core database platform',
            'body' => null,
            'slug' => 'portable-search',
            'type' => 'page',
            'status' => 'published',
        ],
        [
            'title' => 'Unrelated page',
            'excerpt' => 'Does not contain both terms',
            'body' => null,
            'slug' => 'unrelated',
            'type' => 'page',
            'status' => 'published',
        ],
    ]);

    $search = new DatabaseSearch(
        db: $connection,
        table: SEARCH_COMPATIBILITY_TABLE,
    );
    $results = $search->search('port search');
    $fullTextSearch = $fullText->search(
        $connection,
        SEARCH_COMPATIBILITY_TABLE,
        SEARCH_COMPATIBILITY_INDEX,
        array_map(
            static fn (string $column): DatabaseSearchExpression => new DatabaseSearchExpression(
                SqlFragment::raw($grammar->wrap($column)),
            ),
            $columns,
        ),
        'port search',
    );

    expect($results->total())->toBe(1)
        ->and(($results->items()[0] ?? null)?->url)->toBe('/portable-search')
        ->and($fullTextSearch->native)->toBe($index !== null);
});

it('keeps weighted fallback behavior inside the Search package', function (): void {
    $connection = DB::connection();
    $grammar = $connection->getQueryGrammar();
    $fullText = (new DatabaseFullText)->search(
        $connection,
        SEARCH_COMPATIBILITY_TABLE,
        'missing_fulltext_index',
        [
            new DatabaseSearchExpression(
                SqlFragment::raw($grammar->wrap('title')),
                2.0,
            ),
            new DatabaseSearchExpression(
                SqlFragment::raw($grammar->wrap('excerpt')),
                0.0,
            ),
        ],
        'portable search',
    );

    expect($fullText->native)->toBeFalse()
        ->and($fullText->predicate->sql)->toContain('LIKE')
        ->and($fullText->predicate->bindings)->toHaveCount(4)
        ->and($fullText->relevance->bindings)->toContain(2.0)
        ->and($fullText->relevance->bindings)->not->toContain(0.0);
});
