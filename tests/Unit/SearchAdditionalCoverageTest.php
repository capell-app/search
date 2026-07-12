<?php

declare(strict_types=1);

use Capell\Search\Actions\ResolveSearchSettingAction;
use Capell\Search\Drivers\DatabaseSearch;
use Capell\Search\Drivers\ScoutSearch;
use Capell\Search\Tests\Fixtures\SearchAdditionalCoverageScoutModel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('resolves search settings from config when no persisted settings exist', function (): void {
    config(['capell-search.logging.enabled' => false]);

    expect(ResolveSearchSettingAction::run('logging_enabled', 'capell-search.logging.enabled', true))->toBeFalse();
});

it('maps scout search results to normalized result data', function (): void {
    SearchAdditionalCoverageScoutModel::fakeRecords([
        [
            'title' => 'Capell Guide',
            'body' => 'Capell CMS search coverage',
            'path' => 'docs/capell',
            'kind' => 'guide',
        ],
    ]);

    $search = new ScoutSearch(SearchAdditionalCoverageScoutModel::class, 'path', 'kind', 8);

    $results = $search->search('capell', perPage: 5, page: 1, siteId: 12, languageId: 34);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->total())->toBe(1)
        ->and($results->currentPage())->toBe(1)
        ->and($results->items()[0]->title)->toBe('Capell Guide')
        ->and($results->items()[0]->url)->toBe('/docs/capell')
        ->and($results->items()[0]->excerpt)->toBe('Capell C...')
        ->and($results->items()[0]->type)->toBe('guide')
        ->and($results->items()[0]->score)->toBe(2.0);
});

it('searches database-backed public records with context filters and safe highlighting', function (): void {
    Schema::create('search_documents', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('kind');
        $table->text('body');
        $table->unsignedBigInteger('site_id');
        $table->unsignedBigInteger('language_id');
        $table->string('status');
    });

    DB::table('search_documents')->insert([
        [
            'title' => 'Capell implementation guide',
            'slug' => 'docs/capell-implementation',
            'kind' => 'guide',
            'body' => str_repeat('Capell CMS rollout ', 20),
            'site_id' => 10,
            'language_id' => 20,
            'status' => 'published',
        ],
        [
            'title' => 'Capell draft',
            'slug' => 'drafts/capell',
            'kind' => 'draft',
            'body' => 'Capell draft body',
            'site_id' => 10,
            'language_id' => 20,
            'status' => 'draft',
        ],
        [
            'title' => 'Other site Capell page',
            'slug' => 'other/capell',
            'kind' => 'page',
            'body' => 'Capell on another site',
            'site_id' => 11,
            'language_id' => 20,
            'status' => 'published',
        ],
    ]);

    $search = new DatabaseSearch(
        db: DB::connection(),
        table: 'search_documents',
        columns: ['title', 'body', 'missing_column'],
        urlColumn: 'slug',
        typeColumn: 'kind',
        titleColumn: 'title',
        excerptColumn: 'body',
        bodyColumn: 'body',
    );

    $results = $search->search(' Capell ', perPage: 200, page: 0, siteId: 10, languageId: 20);
    $shortQueryResults = $search->search('c', perPage: 0, page: 0);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->total())->toBe(1)
        ->and($results->perPage())->toBe(100)
        ->and($results->currentPage())->toBe(1)
        ->and($results->items()[0]->title)->toBe('Capell implementation guide')
        ->and($results->items()[0]->url)->toBe('/docs/capell-implementation')
        ->and($results->items()[0]->type)->toBe('guide')
        ->and($results->items()[0]->excerpt)->toEndWith('...')
        ->and($results->items()[0]->score)->toBeGreaterThan(1)
        ->and($shortQueryResults->total())->toBe(0)
        ->and($shortQueryResults->perPage())->toBe(1)
        ->and($search->highlight('<Capell>', 'capell'))->toBe('&lt;<mark>Capell</mark>&gt;')
        ->and($search->highlight('<Capell>', ''))->toBe('&lt;Capell&gt;');
});

/**
 * @implements Arrayable<string, mixed>
 */
