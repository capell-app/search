<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Search\Actions\BuildSearchPageViewDataAction;
use Capell\Search\Actions\SeedSearchScreenshotFixtureAction;
use Capell\Search\Actions\SeedSearchScreenshotWidgetAction;
use Capell\Search\Filament\Widgets\TopSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\TrendingSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\ZeroResultSearchesFilamentWidget;
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

    app(SeedSearchScreenshotWidgetAction::class)->handle();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return view('workbench::screenshot-fixtures.widget', ['widget' => $class]);
});

Route::middleware('web')->get('/screenshot-fixtures/search/header', static function (Request $request): View {
    $page = app(SeedSearchScreenshotFixtureAction::class)->handle();
    $site = $page->site;
    abort_unless($site instanceof Site, 500);
    $request->attributes->set('site', $site);
    $request->attributes->set('language', $site->language);
    $request->query->set('q', 'Capell publishing guide');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return view()->file(__DIR__ . '/../resources/views/header.blade.php', BuildSearchPageViewDataAction::run($request)->toViewData());
});
