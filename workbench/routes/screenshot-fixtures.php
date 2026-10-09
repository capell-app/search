<?php

declare(strict_types=1);

use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\Frontend\Actions\BuildPublicPageRenderDataAction;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Data\FrontendRenderContextData;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Search\Actions\BuildSearchPageViewDataAction;
use Capell\Search\Actions\SeedSearchScreenshotFixtureAction;
use Capell\Search\Actions\SeedSearchScreenshotWidgetAction;
use Capell\Search\Filament\Widgets\TopSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\TrendingSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\ZeroResultSearchesFilamentWidget;
use Capell\Search\Http\Controllers\SearchController;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->get('/screenshot-fixtures/search/widgets/{widget}', static function (string $widget): View {
    abort_unless(app()->environment('local', 'testing') && getenv('CAPELL_SCREENSHOT_FIXTURE') === 'record-state', 403);

    $class = match ($widget) {
        'top' => TopSearchesFilamentWidget::class,
        'trending' => TrendingSearchesFilamentWidget::class,
        'zero-results' => ZeroResultSearchesFilamentWidget::class,
        default => abort(404),
    };

    resolve(SeedSearchScreenshotWidgetAction::class)->handle();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    return view('workbench::screenshot-fixtures.widget', ['widget' => $class]);
});

Route::middleware('web')->get('/screenshot-fixtures/search/header', static function (Request $request): View {
    $page = resolve(SeedSearchScreenshotFixtureAction::class)->handle();
    $site = $page->site;
    abort_unless($site instanceof Site, 500);
    $request->attributes->set('site', $site);
    $request->attributes->set('language', $site->language);
    $request->query->set('q', 'Capell publishing guide');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return view()->file(__DIR__ . '/../resources/views/header.blade.php', BuildSearchPageViewDataAction::run($request)->toViewData());
});

Route::middleware('web')->get('/screenshot-fixtures/search/results', static function (Request $request): View {
    $page = resolve(SeedSearchScreenshotFixtureAction::class)->handle();
    $site = $page->site;
    abort_unless($site instanceof Site, 500);
    $request->attributes->set('site', $site);
    $request->attributes->set('language', $site->language);
    $request->query->set('q', 'Capell publishing guide');

    $layout = Layout::query()->where('site_id', $site->getKey())->first() ?? Layout::factory()->site($site)->create();
    $theme = $site->theme;
    abort_unless($site->language !== null && $theme !== null, 500);
    app()->instance(FrontendContextReader::class, (new FrontendState)
        ->withSite($site)
        ->withLanguage($site->language)
        ->withPage($page)
        ->withLayout($layout)
        ->withTheme($theme));
    Frontend::clearResolvedInstance(FrontendContextReader::class);

    // The real frontend pipeline resolves the theme stylesheets into a resource plan;
    // this harness route bypasses it, so resolve the same plan for the head component.
    $renderData = BuildPublicPageRenderDataAction::run(new FrontendRenderContextData(
        page: $page,
        site: $site,
        language: $site->language,
        layout: $layout,
        theme: $theme,
    ));
    Frontend::setFrontendData('runtimeManifest', $renderData->runtimeManifest);
    Frontend::setFrontendData('resourcePlan', $renderData->resourcePlan);

    return resolve(SearchController::class)($request);
});
