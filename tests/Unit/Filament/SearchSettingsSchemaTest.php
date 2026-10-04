<?php

declare(strict_types=1);

use Capell\Search\Enums\SearchDriver;
use Capell\Search\Filament\Settings\SearchSettingsSchema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as LivewireComponent;
use PHPUnit\Framework\Assert;

it('builds search settings with driver, logging, and privacy controls', function (): void {
    $schema = SearchSettingsSchema::make(Schema::make());
    $components = flattenSearchSettingsComponents($schema);
    $driverSelect = collect($components)
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'driver');

    expect($schema)->toHaveCount(2)
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
            'synonyms',
            'typo_corrections',
            'typo_terms',
            'typo_max_distance',
            'promoted_results',
            'queries',
            'title',
            'url',
            'excerpt',
            'type',
            'score',
        )
        ->and($driverSelect)->toBeInstanceOf(Select::class)
        ->and(array_keys($driverSelect->getOptions()))->toBe(array_map(
            static fn (SearchDriver $driver): string => $driver->value,
            SearchDriver::cases(),
        ));
});

it('exposes configured searchable source toggles', function (): void {
    config()->set('capell-search.searchables', [
        'extensions' => [
            'label' => 'Extensions',
            'model' => Model::class,
            'type' => 'extension',
            'enabled' => true,
        ],
    ]);

    $schema = SearchSettingsSchema::make(Schema::make());

    expect(searchSettingsComponentNames($schema))->toContain('sources.extensions.enabled');
});

it('adds translated helper text to operational and promoted result settings', function (): void {
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };
    $schema = Schema::make($livewire);
    $schema->components(fn (): array => SearchSettingsSchema::make($schema));

    $components = array_values(array_filter(
        $schema->getComponents(),
        static fn (mixed $component): bool => $component instanceof Component,
    ));
    $components = flattenSearchSettingsComponentsWithChildren($components);

    $helperTranslations = [
        'driver' => __('capell-search::settings.driver_helper'),
        'results_per_page' => __('capell-search::settings.results_per_page_helper'),
        'log_retention_days' => __('capell-search::settings.log_retention_days_helper'),
        'minimum_query_length' => __('capell-search::settings.minimum_query_length_helper'),
        'queries' => __('capell-search::settings.promoted_queries_helper'),
        'title' => __('capell-search::settings.promoted_title_helper'),
        'url' => __('capell-search::settings.promoted_url_helper'),
        'excerpt' => __('capell-search::settings.promoted_excerpt_helper'),
        'type' => __('capell-search::settings.promoted_type_helper'),
        'score' => __('capell-search::settings.promoted_score_helper'),
    ];

    foreach ($helperTranslations as $field => $translation) {
        expect($translation)
            ->toBeString()
            ->not->toBeEmpty()
            ->not->toBe("capell-search::settings.{$field}_helper");

        $component = collect($components)
            ->first(fn (mixed $component): bool => $component instanceof Component
                && method_exists($component, 'getName')
                && $component->getName() === $field);

        Assert::assertInstanceOf(Component::class, $component);
        expect(searchSettingsHelperText($component))->toBe($translation);
    }

    $promotedResults = collect($components)
        ->first(fn (mixed $component): bool => $component instanceof Repeater
            && $component->getName() === 'promoted_results');

    Assert::assertInstanceOf(Repeater::class, $promotedResults);
    $repeaterHelper = $promotedResults->getChildComponents('below_content');

    Assert::assertCount(1, $repeaterHelper);
    Assert::assertInstanceOf(Text::class, $repeaterHelper[0]);
    expect($repeaterHelper[0]->getContent())
        ->toBe(__('capell-search::settings.promoted_results_helper'));

    expect($helperTranslations['results_per_page'])
        ->toContain('public search results route', 'organic results', 'page one')
        ->and($helperTranslations['minimum_query_length'])
        ->toContain('characters', 'normalisation', 'no results')
        ->and($helperTranslations['log_retention_days'])
        ->toContain('searched_at', 'deletes')
        ->and($helperTranslations['queries'])
        ->toContain('normalised query', 'exactly')
        ->and($helperTranslations['score'])
        ->toContain('does not alter organic result ranking')
        ->and(__('capell-search::settings.promoted_results_helper'))
        ->toContain('first results page', 'ahead of organic results')
        ->toContain('does not alter organic ranking');
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

function searchSettingsHelperText(Component $component): string
{
    $afterLabel = $component->getChildComponents('after_label');

    Assert::assertCount(1, $afterLabel);
    Assert::assertInstanceOf(Icon::class, $afterLabel[0]);

    $tooltip = $afterLabel[0]->getTooltip();
    Assert::assertIsString($tooltip);

    return $tooltip;
}

/**
 * @param  array<array-key, Component>  $components
 * @return list<Component>
 */
function flattenSearchSettingsComponentsWithChildren(array $components): array
{
    /** @var list<Component> $flattenedComponents */
    $flattenedComponents = [];

    foreach ($components as $component) {
        $flattenedComponents[] = $component;

        foreach ($component->getChildSchemas() as $childSchema) {
            $childComponents = array_values(array_filter(
                $childSchema->getComponents(),
                static fn (mixed $childComponent): bool => $childComponent instanceof Component,
            ));

            array_push($flattenedComponents, ...flattenSearchSettingsComponentsWithChildren($childComponents));
        }

        if ($component instanceof Repeater) {
            $childSchema = $component->getChildSchema();
            if ($childSchema instanceof Schema) {
                $childComponents = array_values(array_filter(
                    $childSchema->getComponents(),
                    static fn (mixed $childComponent): bool => $childComponent instanceof Component,
                ));

                array_push($flattenedComponents, ...flattenSearchSettingsComponentsWithChildren($childComponents));
            }
        }
    }

    return $flattenedComponents;
}
