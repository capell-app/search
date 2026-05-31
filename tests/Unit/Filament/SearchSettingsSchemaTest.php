<?php

declare(strict_types=1);

use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

it('builds search settings with driver, logging, and privacy controls', function (): void {
    $schema = SearchSettingsSchema::make(Schema::make());
    $components = flattenSearchSettingsComponents($schema);
    $driverSelect = collect($components)
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'driver');

    expect($schema)->toHaveCount(1)
        ->and($schema[0])->toBeInstanceOf(Grid::class)
        ->and(searchSettingsComponentNames($schema))->toContain(
            'enabled',
            'show_header_search',
            'driver',
            'results_per_page',
            'record_search_logs',
            'log_retention_days',
            'hash_visitor_data',
            'minimum_query_length',
        )
        ->and($driverSelect)->toBeInstanceOf(Select::class)
        ->and(array_keys($driverSelect->getOptions()))->toBe(array_map(
            static fn (SearchDriver $driver): string => $driver->value,
            SearchDriver::cases(),
        ));
});

/**
 * @param  array<int, mixed>  $components
 * @return array<int, mixed>
 */
function flattenSearchSettingsComponents(array $components): array
{
    $flattenedComponents = [];

    foreach ($components as $component) {
        $flattenedComponents[] = $component;
        if (! is_object($component)) {
            continue;
        }

        if (! method_exists($component, 'getDefaultChildComponents')) {
            continue;
        }

        $childComponents = $component->getDefaultChildComponents();

        if (is_array($childComponents)) {
            array_push($flattenedComponents, ...flattenSearchSettingsComponents($childComponents));
        }
    }

    return $flattenedComponents;
}

/**
 * @param  array<int, mixed>  $components
 * @return array<int, string>
 */
function searchSettingsComponentNames(array $components): array
{
    return collect(flattenSearchSettingsComponents($components))
        ->filter(fn (mixed $component): bool => is_object($component) && method_exists($component, 'getName'))
        ->map(fn (mixed $component): string => $component->getName())
        ->values()
        ->all();
}
