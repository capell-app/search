<?php

declare(strict_types=1);

use Capell\Search\Actions\BuildSearchFacetGroupsAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

test('builds public facet groups with counts and toggle urls', function (): void {
    $registry = new SearchableSourceRegistry;
    $registry->register(new SearchableSourceData(
        key: 'pages',
        label: 'Pages',
        modelClass: Model::class,
        type: 'page',
    ));
    $registry->register(new SearchableSourceData(
        key: 'articles',
        label: 'Articles',
        modelClass: Model::class,
        type: 'article',
    ));

    $search = new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            $total = match (true) {
                $filters?->types === ['page'] => 3,
                $filters?->types === ['article'] => 2,
                $filters?->sourceKeys === ['pages'] => 3,
                $filters?->sourceKeys === ['articles'] => 2,
                default => 0,
            };

            return new Paginator(
                new Collection([new SearchResultData('Result', '/result', 'Excerpt')]),
                $total,
                $perPage,
                $page,
            );
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    };

    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, [
        'q' => 'capell',
        'type' => ['page'],
        'page' => 4,
    ]);

    $groups = runBoundAction(
        BuildSearchFacetGroupsAction::class,
        new BuildSearchFacetGroupsAction($search, $registry),
        request: $request,
        query: 'capell',
        filters: new SearchFilterData(types: ['page']),
    );

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->key)->toBe('type')
        ->and($groups[0]->options)->toHaveCount(2)
        ->and($groups[0]->options[0]->key)->toBe('article')
        ->and($groups[0]->options[0]->count)->toBe(2)
        ->and($groups[0]->options[0]->url)->toBe(route('capell-frontend.search', ['q' => 'capell', 'type' => ['page', 'article']]))
        ->and($groups[0]->options[1]->key)->toBe('page')
        ->and($groups[0]->options[1]->selected)->toBeTrue()
        ->and($groups[0]->options[1]->url)->toBe(route('capell-frontend.search', ['q' => 'capell']))
        ->and($groups[1]->key)->toBe('source')
        ->and($groups[1]->options)->toHaveCount(2);
});

test('does not build facets for blank or disabled filter requests', function (): void {
    $registry = new SearchableSourceRegistry;
    $search = new class implements Search
    {
        public function search(
            string $query,
            int $perPage = 10,
            int $page = 1,
            ?int $siteId = null,
            ?int $languageId = null,
            ?SearchFilterData $filters = null,
        ): LengthAwarePaginator {
            throw new RuntimeException('Facet counts should not run for blank or disabled filters.');
        }

        public function highlight(string $text, string $query): string
        {
            return $text;
        }
    };

    $action = new BuildSearchFacetGroupsAction($search, $registry);
    $request = Request::create('/search', Symfony\Component\HttpFoundation\Request::METHOD_GET, ['q' => '']);

    expect($action->handle($request, '', new SearchFilterData))->toBe([]);

    config()->set('capell-search.filters.enabled', false);

    expect($action->handle($request, 'capell', new SearchFilterData))->toBe([]);

    config()->set('capell-search.filters.enabled', true);
    config()->set('capell-search.filters.facet_groups', false);

    expect($action->handle($request, 'capell', new SearchFilterData))->toBe([]);
});
