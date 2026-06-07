<?php

declare(strict_types=1);

namespace Capell\Search\Filament\Settings;

use Capell\Admin\Filament\Contracts\HasSchema;
use Capell\Admin\Filament\Support\HelperText;
use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Enums\SearchDriver;
use Capell\Search\Support\SearchableSourceRegistry;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SearchSettingsSchema implements HasSchema
{
    public static function make(Schema $configurator): array
    {
        return [
            Grid::make(2)
                ->columnSpanFull()
                ->schema([
                    HelperText::apply(
                        Toggle::make('enabled')
                            ->label(__('capell-search::settings.enabled')),
                        'capell-search::settings.enabled_helper',
                    ),
                    HelperText::apply(
                        Toggle::make('show_header_search')
                            ->label(__('capell-search::settings.show_header_search')),
                        'capell-search::settings.show_header_search_helper',
                    ),
                    Select::make('driver')
                        ->label(__('capell-search::settings.driver'))
                        ->options(SearchDriver::class)
                        ->required(),
                    TextInput::make('results_per_page')
                        ->label(__('capell-search::settings.results_per_page'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(50),
                    HelperText::apply(
                        Toggle::make('record_search_logs')
                            ->label(__('capell-search::settings.record_search_logs')),
                        'capell-search::settings.record_search_logs_helper',
                    ),
                    TextInput::make('log_retention_days')
                        ->label(__('capell-search::settings.log_retention_days'))
                        ->integer()
                        ->minValue(1)
                        ->suffix(__('capell-admin::form.days')),
                    HelperText::apply(
                        Toggle::make('hash_visitor_data')
                            ->label(__('capell-search::settings.hash_visitor_data')),
                        'capell-search::settings.hash_visitor_data_helper',
                    ),
                    TextInput::make('minimum_query_length')
                        ->label(__('capell-search::settings.minimum_query_length'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(10),
                    ...self::searchableSourceToggles(),
                ]),
            Section::make(__('capell-search::settings.curation'))
                ->description(__('capell-search::settings.curation_helper'))
                ->columnSpanFull()
                ->schema([
                    KeyValue::make('synonyms')
                        ->label(__('capell-search::settings.synonyms'))
                        ->helperText(__('capell-search::settings.synonyms_helper'))
                        ->keyLabel(__('capell-search::settings.synonym_term'))
                        ->valueLabel(__('capell-search::settings.synonym_aliases'))
                        ->columnSpanFull(),
                    KeyValue::make('typo_corrections')
                        ->label(__('capell-search::settings.typo_corrections'))
                        ->helperText(__('capell-search::settings.typo_corrections_helper'))
                        ->keyLabel(__('capell-search::settings.typo_misspelling'))
                        ->valueLabel(__('capell-search::settings.typo_correction'))
                        ->columnSpanFull(),
                    TagsInput::make('typo_terms')
                        ->label(__('capell-search::settings.typo_terms'))
                        ->helperText(__('capell-search::settings.typo_terms_helper'))
                        ->columnSpanFull(),
                    TextInput::make('typo_max_distance')
                        ->label(__('capell-search::settings.typo_max_distance'))
                        ->helperText(__('capell-search::settings.typo_max_distance_helper'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(3),
                    Repeater::make('promoted_results')
                        ->label(__('capell-search::settings.promoted_results'))
                        ->helperText(__('capell-search::settings.promoted_results_helper'))
                        ->schema([
                            TagsInput::make('queries')
                                ->label(__('capell-search::settings.promoted_queries'))
                                ->required(),
                            TextInput::make('title')
                                ->label(__('capell-search::settings.promoted_title'))
                                ->required(),
                            TextInput::make('url')
                                ->label(__('capell-search::settings.promoted_url'))
                                ->required(),
                            Textarea::make('excerpt')
                                ->label(__('capell-search::settings.promoted_excerpt'))
                                ->rows(2)
                                ->columnSpanFull(),
                            TextInput::make('type')
                                ->label(__('capell-search::settings.promoted_type'))
                                ->default('page'),
                            TextInput::make('score')
                                ->label(__('capell-search::settings.promoted_score'))
                                ->numeric()
                                ->default(1000.0),
                        ])
                        ->defaultItems(0)
                        ->collapsible()
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return list<Toggle>
     */
    private static function searchableSourceToggles(): array
    {
        return array_values(collect(self::searchableSourceLabels())
            ->map(
                static fn (string $label, string $key): Toggle => HelperText::apply(
                    Toggle::make(sprintf('sources.%s.enabled', $key))
                        ->label($label),
                    'capell-search::settings.searchable_source_helper',
                ),
            )
            ->all());
    }

    /**
     * @return array<string, string>
     */
    private static function searchableSourceLabels(): array
    {
        $sources = [];

        if (app()->bound(SearchableSourceRegistry::class)) {
            /** @var SearchableSourceRegistry $registry */
            $registry = resolve(SearchableSourceRegistry::class);

            $sources = $registry
                ->all()
                ->mapWithKeys(static fn (SearchableSourceData $source): array => [$source->key => $source->label])
                ->all();
        }

        $configuredSources = config('capell-search.searchables', []);

        if (! is_array($configuredSources)) {
            return $sources;
        }

        foreach ($configuredSources as $key => $source) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_array($source)) {
                continue;
            }

            $sources[$key] = (string) ($source['label'] ?? $key);
        }

        return $sources;
    }
}
