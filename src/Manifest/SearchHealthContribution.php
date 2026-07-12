<?php

declare(strict_types=1);

namespace Capell\Search\Manifest;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;

final class SearchHealthContribution implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
