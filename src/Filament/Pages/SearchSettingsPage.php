<?php

declare(strict_types=1);

namespace Capell\Search\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Capell\Admin\Filament\Pages\AbstractPackageSettingsPage;

final class SearchSettingsPage extends AbstractPackageSettingsPage
{
    use HasPageShield;

    protected static string $settingsGroup = 'search';

    protected static ?string $slug = 'extensions/search/settings';
}
