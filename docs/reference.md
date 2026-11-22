# Search reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/search`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Search is an **Available** Capell package in the **Capell Search & SEO** product group. It ships as `capell-app/search` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: paid
- Bundle: search-seo
- Composer package: `capell-app/search`
- Namespace: `Capell\Search`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Search\Providers\SearchServiceProvider`
- `Capell\Search\Providers\MigrationMetadataServiceProvider`
- `Capell\Search\Providers\ConsoleServiceProvider`
- `Capell\Search\Providers\AdminServiceProvider`

### Config files

- `packages/search/config/capell-search.php`

### Migrations

- `packages/search/database/migrations/2026_05_10_190868_01_create_search_logs_table.php`
- `packages/search/database/migrations/2026_05_21_000002_add_fulltext_index_to_search_database_table.php`
- `packages/search/database/migrations/2026_07_12_000001_encrypt_search_log_pii.php`
- `packages/search/database/migrations/2026_09_29_120000_remove_implicit_timestamp_updates_from_search.php`
- `packages/search/database/migrations/2026_11_11_000001_add_search_reference_to_search_logs.php`

### Settings migrations

- `packages/search/database/settings/2026_05_10_190869_01_add_search_settings.php`

### Settings classes

- `SearchSettings`

### Models

- `SearchLog`

### Filament classes

- `SearchSettingsPage`
- `SearchDashboardSettingsContributor`
- `SearchSettingsSchema`
- `BuildsSearchInsightsWindow`
- `SearchOverviewStatsFilamentWidget`
- `TopSearchesFilamentWidget`
- `TrendingSearchesFilamentWidget`
- `ZeroResultSearchesFilamentWidget`

### Route files

- `packages/search/routes/web.php`

### Extension contracts

- `Search`

### Actions

- `ApplySearchResultEnhancementsAction`
- `BuildAutocompleteQuerySuggestionsAction`
- `BuildSearchFacetGroupsAction`
- `BuildSearchPageViewDataAction`
- `BuildTopClickedResultsQueryAction`
- `BuildTopSearchesQueryAction`
- `BuildTrendingSearchesQueryAction`
- `BuildZeroResultSearchesQueryAction`
- `CanCollectSearchAnalyticsAction`
- `CreatePromotedResultFromZeroResultSearchAction`
- `CreateSearchVisitorIdentityAction`
- `CreateSynonymFromZeroResultSearchAction`
- `EncryptLegacySearchLogPiiAction`
- `FlushScoutSearchSourcesAction`
- `FlushSiteDiscoverySearchRegistryAction`
- `GenerateSearchClickTokenAction`
- `HashSearchRetentionValueAction`
- `IndexScoutSearchSourcesAction`
- `InstallSearchPackageAction`
- `NormalizeSearchFiltersAction`
- `NormalizeSearchQueryAction`
- `ProbeScoutIndexHealthAction`
- `PublishSearchAssetsAction`
- `PurgeSearchLogsAction`
- `RecordSearchAction`
- `RecordSearchResultClickAction`
- `RedactSearchQueryAction`
- `RegisterConfiguredSearchableSourcesAction`
- `ResolveCorrectedSearchQueryAction`
- `ResolveExpandedSearchQueriesAction`
- `ResolvePromotedSearchDestinationAction`
- `ResolvePromotedSearchResultsAction`
- `ResolveSearchDriverAction`
- `ResolveSearchResultTypeLabelAction`
- `ResolveSearchSettingAction`
- `ResolveSiteScopedSynonymsAction`
- `RunAutocompleteSearchAction`
- `RunSearchAction`
- `SanitizeSearchResultAction`
- `SeedSearchScreenshotFixtureAction`
- `SeedSearchScreenshotWidgetAction`

### Data objects

- `AutocompleteQuerySuggestionData`
- `AutocompleteSearchResponseData`
- `AutocompleteSearchResultData`
- `DatabaseFullTextSearch`
- `DatabaseSearchExpression`
- `PromotedSearchDestinationData`
- `PromotedSearchResultData`
- `SearchFacetGroupData`
- `SearchFacetOptionData`
- `SearchFilterData`
- `SearchInsightsWindowData`
- `SearchPageViewData`
- `SearchQueryMetadataData`
- `SearchRequestData`
- `SearchResultData`
- `SearchTermSummaryData`
- `SearchVisitorIdentityData`
- `SearchableSourceData`

### Command signatures

- `capell:search:screenshot-fixture`
- `search:flush`
- `search:index`
- `search:purge`

### Manifest action API

- `applySearchResultEnhancements: Capell\Search\Actions\ApplySearchResultEnhancementsAction`
- `buildTopClickedResultsQuery: Capell\Search\Actions\BuildTopClickedResultsQueryAction`
- `buildTopSearchesQuery: Capell\Search\Actions\BuildTopSearchesQueryAction`
- `buildTrendingSearchesQuery: Capell\Search\Actions\BuildTrendingSearchesQueryAction`
- `buildZeroResultSearchesQuery: Capell\Search\Actions\BuildZeroResultSearchesQueryAction`
- `createPromotedResultFromZeroResultSearch: Capell\Search\Actions\CreatePromotedResultFromZeroResultSearchAction`
- `createSynonymFromZeroResultSearch: Capell\Search\Actions\CreateSynonymFromZeroResultSearchAction`
- `flushScoutSearchSources: Capell\Search\Actions\FlushScoutSearchSourcesAction`
- `indexScoutSearchSources: Capell\Search\Actions\IndexScoutSearchSourcesAction`
- `install: Capell\Search\Actions\InstallSearchPackageAction`
- `normalizeSearchQuery: Capell\Search\Actions\NormalizeSearchQueryAction`
- `purgeSearchLogs: Capell\Search\Actions\PurgeSearchLogsAction`
- `recordSearch: Capell\Search\Actions\RecordSearchAction`
- `recordSearchResultClick: Capell\Search\Actions\RecordSearchResultClickAction`
- `resolveExpandedSearchQueries: Capell\Search\Actions\ResolveExpandedSearchQueriesAction`
- `resolvePromotedSearchResults: Capell\Search\Actions\ResolvePromotedSearchResultsAction`
- `runSearch: Capell\Search\Actions\RunSearchAction`

### Scheduled commands

- `search:purge (monthly; package registered)`

### Console command classes

- `FlushSearchCommand`
- `IndexSearchCommand`
- `PurgeSearchLogsCommand`
- `SeedSearchScreenshotFixtureCommand`

### Manifest contributions

- `admin-page: Capell\Search\Manifest\SearchSettingsPageContribution`
- `agent-capability: Capell\Search\Manifest\SearchAgentToolContribution`
- `console-command: Capell\Search\Manifest\SearchConsoleCommandsContribution`
- `dashboard-widget: Capell\Search\Manifest\TopSearchesWidgetContribution`
- `dashboard-widget: Capell\Search\Manifest\TrendingSearchesWidgetContribution`
- `dashboard-widget: Capell\Search\Manifest\ZeroResultSearchesWidgetContribution`
- `health-check: Capell\Search\Manifest\SearchHealthContribution`
- `migration: Capell\Search\Manifest\SearchMigrationsContribution`
- `model: Capell\Search\Manifest\SearchLogModelContribution`
- `overview-stat: Capell\Search\Manifest\SearchOverviewStatsContribution`
- `route: Capell\Search\Manifest\SearchFrontendRouteContribution`
- `scheduled-job: Capell\Search\Manifest\SearchLogPurgeScheduleContribution`
- `setting: Capell\Search\Manifest\SearchSettingsContribution`

### Health checks

- `Capell\Search\Health\SearchHealthCheck`

### Blade views

- `packages/search/resources/views/components/facets.blade.php`
- `packages/search/resources/views/components/form.blade.php`
- `packages/search/resources/views/components/header/autocomplete-results.blade.php`
- `packages/search/resources/views/components/header/search-dialog.blade.php`
- `packages/search/resources/views/components/header/search-modal.blade.php`
- `packages/search/resources/views/components/header/search-trigger.blade.php`
- `packages/search/resources/views/components/results.blade.php`
- `packages/search/resources/views/filament/widgets/search-overview-stats.blade.php`
- `packages/search/resources/views/layouts/frontend.blade.php`
- `packages/search/resources/views/pages/search.blade.php`

### Cache tags

- `search`


## Data Model

- Required tables: `search_logs`.
- Models: `SearchLog`.
- Core record references in migrations: `sites via site_id`, `languages via language_id`.
- Migration files: `2026_05_10_190868_01_create_search_logs_table.php`, `2026_05_21_000002_add_fulltext_index_to_search_database_table.php`, `2026_07_12_000001_encrypt_search_log_pii.php`, `2026_09_29_120000_remove_implicit_timestamp_updates_from_search.php`, `2026_11_11_000001_add_search_reference_to_search_logs.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare null-on-delete relationships; retention is scheduled through `search:purge` (monthly; registered by the package provider).

<!-- prettier-ignore-end -->
