<?php

declare(strict_types=1);

namespace Capell\Search\Support\RenderHooks;

use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Capell\Search\Actions\ResolveSearchSettingAction;

final class RegisterHeaderSearchHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        if (! (bool) ResolveSearchSettingAction::run(
            'enabled',
            'capell-search.enabled',
            true,
        )) {
            return '';
        }

        if (! (bool) ResolveSearchSettingAction::run(
            'show_header_search',
            'capell-search.show_header_search',
            true,
        )) {
            return '';
        }

        return view('capell-search::components.header.search-modal')->render();
    }
}
