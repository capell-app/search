<?php

declare(strict_types=1);

namespace Capell\Search\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;

final class SearchFrontendRouteContribution implements ExtensionContribution, RegistersExtensionRoute
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
