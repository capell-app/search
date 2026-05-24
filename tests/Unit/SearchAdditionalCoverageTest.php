<?php

declare(strict_types=1);

use Capell\Search\Actions\ResolveSearchSettingAction;
use Capell\Search\Drivers\ScoutSearch;
use Illuminate\Pagination\LengthAwarePaginator;

it('resolves search settings from config when no persisted settings exist', function (): void {
    config(['capell-search.logging.enabled' => false]);

    expect(ResolveSearchSettingAction::run('logging_enabled', 'capell-search.logging.enabled', true))->toBeFalse();
});

it('maps scout search results to normalized result data', function (): void {
    $search = new ScoutSearch(SearchAdditionalCoverageScoutModel::class, 'path', 'kind', 8);

    $results = $search->search('capell', perPage: 5, page: 2);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->total())->toBe(1)
        ->and($results->currentPage())->toBe(2)
        ->and($results->items()[0]->title)->toBe('Capell Guide')
        ->and($results->items()[0]->url)->toBe('/docs/capell')
        ->and($results->items()[0]->excerpt)->toBe('Capell C...')
        ->and($results->items()[0]->type)->toBe('guide')
        ->and($results->items()[0]->score)->toBe(2.0);
});

final class SearchAdditionalCoverageScoutModel
{
    public static function search(string $query): SearchAdditionalCoverageScoutBuilder
    {
        return new SearchAdditionalCoverageScoutBuilder($query);
    }
}

final class SearchAdditionalCoverageScoutBuilder
{
    public function __construct(private readonly string $query) {}

    /**
     * @return LengthAwarePaginator<array-key, mixed>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        expect($this->query)->toBe('capell');

        return new LengthAwarePaginator([
            new SearchAdditionalCoverageScoutRecord([
                'title' => 'Capell Guide',
                'body' => 'Capell CMS search coverage',
                'path' => 'docs/capell',
                'kind' => 'guide',
            ]),
        ], 1, $perPage, $page);
    }
}

final class SearchAdditionalCoverageScoutRecord
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private readonly array $attributes) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
