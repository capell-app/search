<?php

declare(strict_types=1);

namespace Capell\Search\Http\Controllers;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Search\Actions\BuildSearchPageViewDataAction;
use Capell\Search\Actions\RecordSearchResultClickAction;
use Capell\Search\Actions\RunAutocompleteSearchAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;

final class SearchController
{
    public function __invoke(Request $request): View
    {
        $viewData = BuildSearchPageViewDataAction::run($request)->toViewData();
        $pageView = $this->configuredPageView();

        if ($pageView !== null) {
            return view($pageView, $viewData);
        }

        $content = view('capell-search::pages.search', $viewData);
        $shellData = $this->frontendShellData();

        if ($shellData === null) {
            return $content;
        }

        $slot = view('capell-search::layouts.frontend', $viewData);

        return view('capell::app', [
            ...$shellData,
            'slot' => new HtmlString($slot->render()),
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

    /**
     * @return array{language: Language, layout: Layout, pageRecord: Pageable, site: Site, theme: Theme}|null
     */
    private function frontendShellData(): ?array
    {
        if (! app()->bound(CapellFrontendContext::class)) {
            return null;
        }

        $frontend = app(CapellFrontendContext::class);
        $language = $frontend->language();
        $layout = $frontend->layout();
        $page = $frontend->page();
        $site = $frontend->site();
        $theme = $frontend->theme();

        if (
            ! $language instanceof Language
            || ! $layout instanceof Layout
            || ! $page instanceof Pageable
            || ! $site instanceof Site
            || ! $theme instanceof Theme
        ) {
            return null;
        }

        return [
            'language' => $language,
            'layout' => $layout,
            'pageRecord' => $page,
            'site' => $site,
            'theme' => $theme,
        ];
    }
}
