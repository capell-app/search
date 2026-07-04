<?php

declare(strict_types=1);

namespace Capell\Search\Support\RenderHooks;

use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Capell\Frontend\Facades\Frontend;
use Capell\Search\Actions\ResolveSearchSettingAction;

final class RegisterHeaderSearchHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $enabled = Frontend::getFrontendData('search.header.enabled');
        $visible = Frontend::getFrontendData('search.header.visible');

        $enabled ??= ResolveSearchSettingAction::run('enabled', 'capell-search.enabled', true);
        $visible ??= ResolveSearchSettingAction::run('show_header_search', 'capell-search.show_header_search', true);

        if (! (bool) $enabled) {
            return '';
        }

        if (! (bool) $visible) {
            return '';
        }

        return view('capell-search::components.header.search-modal')->render();
    }
}
