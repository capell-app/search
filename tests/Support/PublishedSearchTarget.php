<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Support;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;

/**
 * Publishes a page at a path on the site served from a host, so a promotion
 * that points there can be shown to target a published page.
 */
final class PublishedSearchTarget
{
    public static function at(string $path, string $host = 'localhost'): void
    {
        $domain = SiteDomain::query()->where('domain', $host)->first();

        if (! $domain instanceof SiteDomain) {
            $language = Language::query()->first() ?? Language::factory()->english()->create();
            $site = Site::factory()->create(['language_id' => $language->id]);
            $domain = SiteDomain::factory()->site($site)->language($language)->create([
                'domain' => $host,
                'path' => null,
                'scheme' => null,
                'default' => true,
            ]);
        }

        $language = $domain->language ?? Language::query()->firstOrFail();
        $page = Page::factory()->site($domain->site)->published()->withTranslations($language, ['title' => 'Published ' . $path])->create();
        $pageUrl = PageUrl::query()
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->id)
            ->where('language_id', $language->id)
            ->first()
            ?? PageUrl::factory()->site($domain->site)->language($language)->page($page)->make();
        $pageUrl->forceFill(['url' => $path, 'status' => true, 'type' => null])->save();
    }
}
