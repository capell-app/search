<?php

declare(strict_types=1);

namespace Capell\Search\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class SearchSettingsPageContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
