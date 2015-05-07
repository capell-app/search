<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchResultData;
use RuntimeException;

final class SeedSearchScreenshotFixtureAction
{
    public function handle(): Page
    {
        $path = getenv('CAPELL_SCREENSHOT_APP_PATH');
        throw_unless(
            app()->environment(['local', 'testing'])
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($path) && realpath($path) === realpath(base_path()),
            RuntimeException::class,
            'Screenshot fixtures require the explicit disposable local screenshot environment.',
        );

        $site = Site::query()->firstOrFail();
        $page = Page::query()->where('site_id', $site->id)->where('name', 'Capell publishing guide')->first();
        $page ??= Page::factory()->site($site)->withTranslations(data: ['title' => 'Capell publishing guide'])->create(['name' => 'Capell publishing guide']);
        $page->update(['visible_from' => now()->subDay(), 'visible_until' => null]);

        // The default discovery driver reads the real public URL registry.
        // A different configured backend must be indexed before capture.
        throw_unless(
            collect(app(Search::class)->search('Capell publishing guide', siteId: $site->id, languageId: $site->language_id)->items())
                ->contains(fn (SearchResultData $result): bool => $result->title === 'Capell publishing guide'),
            RuntimeException::class,
            'The configured search backend has not indexed the screenshot page. Index it before capture.',
        );

        return $page;
    }
}
