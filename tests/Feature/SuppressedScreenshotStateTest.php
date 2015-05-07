<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Search\Actions\SeedSearchScreenshotFixtureAction;
use Capell\Search\Http\Controllers\SearchController;
use Capell\Tests\Support\CapellManifest;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Capell\Tests\Support\ScreenshotManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(CreatesAdminUser::class);

it('reaches the package specific settings form', function (): void {
    $this->actingAsAdmin();
    $this->get(searchSuppressedCaptureUrl('search-settings-screen'))->assertOk()->assertSee('results_per_page')->assertSee('log_retention_days');
});

it('serves a real header control and indexed autocomplete results', function (): void {
    $domain = SiteDomain::factory()->default()->create();
    require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    try {
        $page = app(SeedSearchScreenshotFixtureAction::class)->handle();
        app(SeedSearchScreenshotFixtureAction::class)->handle();
        $request = Request::create('/search/autocomplete', 'GET', ['q' => 'Capell publishing guide']);
        $request->attributes->set('site', $page->site);
        $site = $page->site;
        throw_unless($site instanceof Site, RuntimeException::class, 'The Search screenshot page has no site.');
        $request->attributes->set('language', $site->language);
        $response = app(SearchController::class)->autocomplete($request);
        expect(collect(searchAutocompleteResults($response->getData(true)))->pluck('title')->all())->toContain('Capell publishing guide');
        $this->get(searchSuppressedCaptureUrl('header-search-field'))->assertOk()->assertSee('data-site-search-trigger', false)->assertSee('data-site-search-input', false)->assertSee('publishing guide');
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
    }
});

it('declares the screenshot seed command in both manifests', function (): void {
    $manifestPath = __DIR__ . '/../../capell.json';
    $screenshotsPath = __DIR__ . '/../../docs/screenshots.json';
    $command = 'capell:search:screenshot-fixture';
    expect(CapellManifest::screenshotFixtureCommand($manifestPath))->toBe($command)
        ->and(ScreenshotManifest::fixtureCommands($screenshotsPath))->toContain($command)
        ->and(CapellManifest::consoleCommandNames($manifestPath))->toContain($command)
        ->and(Artisan::all())->toHaveKey($command);
    $this->artisan($command)->assertFailed();
});

function searchSuppressedCaptureUrl(string $key): string
{
    return ScreenshotManifest::captureUrl(__DIR__ . '/../../docs/screenshots.json', $key);
}

/**
 * @return list<array<array-key, mixed>>
 */
function searchAutocompleteResults(mixed $payload): array
{
    if (! is_array($payload) || ! is_array($payload['results'] ?? null)) {
        throw new RuntimeException('Search autocomplete did not return a results list.');
    }

    $results = [];

    foreach ($payload['results'] as $result) {
        if (! is_array($result)) {
            throw new RuntimeException('Search autocomplete returned a non-object result.');
        }

        $results[] = $result;
    }

    return $results;
}
