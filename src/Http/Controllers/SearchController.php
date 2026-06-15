<?php

declare(strict_types=1);

namespace Capell\Search\Http\Controllers;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\Frontend\Facades\Frontend;
use Capell\Search\Actions\BuildSearchFacetGroupsAction;
use Capell\Search\Actions\GenerateSearchClickTokenAction;
use Capell\Search\Actions\NormalizeSearchFiltersAction;
use Capell\Search\Actions\RecordSearchAction;
use Capell\Search\Actions\RecordSearchResultClickAction;
use Capell\Search\Actions\ResolveSearchSettingAction;
use Capell\Search\Actions\RunAutocompleteSearchAction;
use Capell\Search\Actions\RunSearchAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchRequestData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Throwable;

final class SearchController
{
    public function __invoke(Request $request): View
    {
        $query = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) ResolveSearchSettingAction::run(
            'results_per_page',
            'capell-search.results_per_page',
            10,
        );

        $data = $this->searchRequestData(
            query: $query,
            page: $page,
            perPage: $perPage,
            request: $request,
        );

        $results = RunSearchAction::run($data)->withPath($request->url());
        $highlightedResults = $this->highlightedResults(resolve(Search::class), $results, $query);
        $clickTrackingToken = GenerateSearchClickTokenAction::run($data);
        $facetGroups = BuildSearchFacetGroupsAction::run(
            request: $request,
            query: $query,
            filters: $data->filters ?? new SearchFilterData,
            siteId: $data->siteId,
            languageId: $data->languageId,
        );

        RecordSearchAction::dispatchAfterResponse(
            $data,
            $results->total(),
            $request->ip(),
            $request->userAgent(),
        );

        $pageView = $this->configuredPageView();

        if ($pageView !== null) {
            return view($pageView, [
                'highlightedResults' => $highlightedResults,
                'facetGroups' => $facetGroups,
                'clickTrackingToken' => $clickTrackingToken,
                'query' => $query,
                'results' => $results,
            ]);
        }

        $content = view('capell-search::pages.search', [
            'highlightedResults' => $highlightedResults,
            'facetGroups' => $facetGroups,
            'clickTrackingToken' => $clickTrackingToken,
            'query' => $query,
            'results' => $results,
        ]);

        if (! $this->canRenderFrontendShell()) {
            return $content;
        }

        $slot = view('capell-search::layouts.frontend', [
            'highlightedResults' => $highlightedResults,
            'facetGroups' => $facetGroups,
            'clickTrackingToken' => $clickTrackingToken,
            'query' => $query,
            'results' => $results,
        ]);

        return view('capell::app', [
            'language' => Frontend::language(),
            'layout' => Frontend::layout(),
            'pageRecord' => Frontend::page(),
            'site' => Frontend::site(),
            'slot' => new HtmlString($slot->render()),
            'theme' => Frontend::theme(),
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        abort_unless((bool) config('capell-search.autocomplete.enabled', true), 404);

        return response()->json(RunAutocompleteSearchAction::run($request)->toArray());
    }

    public function click(Request $request): Response
    {
        abort_unless((bool) config('capell-search.click_tracking.enabled', true), 404);

        RecordSearchResultClickAction::run(
            request: $request,
            query: (string) $request->string('query'),
            url: (string) $request->string('url'),
            token: $request->string('token')->toString() ?: null,
            type: $request->string('type')->toString() ?: null,
            position: $request->integer('position') ?: null,
            surface: $request->string('surface')->toString() ?: null,
        );

        return response()->noContent();
    }

    private function searchRequestData(string $query, int $page, int $perPage, Request $request): SearchRequestData
    {
        $site = $request->attributes->get('site');
        $language = $request->attributes->get('language');

        return new SearchRequestData(
            query: $query,
            page: $page,
            perPage: $perPage,
            siteId: is_object($site) ? (int) data_get($site, 'id') : null,
            languageId: is_object($language) ? (int) data_get($language, 'id') : null,
            filters: NormalizeSearchFiltersAction::run($request),
        );
    }

    /**
     * @param  LengthAwarePaginator<int, SearchResultData>  $results
     * @return Collection<int, array{title: string, excerpt: string}>
     */
    private function highlightedResults(Search $search, LengthAwarePaginator $results, string $query): Collection
    {
        return collect($results->items())
            ->map(static fn (SearchResultData $result): array => [
                'title' => $search->highlight($result->title, $query),
                'excerpt' => $search->highlight($result->excerpt, $query),
            ])
            ->values();
    }

    /**
     * @return view-string|null
     */
    private function configuredPageView(): ?string
    {
        $view = config('capell-search.page_view');

        if (! is_string($view) || trim($view) === '') {
            return null;
        }

        return view()->exists($view) ? $view : null;
    }

    private function canRenderFrontendShell(): bool
    {
        try {
            return Frontend::language() instanceof Language
                && Frontend::layout() instanceof Layout
                && Frontend::page() instanceof Pageable
                && Frontend::site() instanceof Site
                && Frontend::theme() instanceof Theme;
        } catch (Throwable) {
            return false;
        }
    }
}
