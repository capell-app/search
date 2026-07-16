# Search

<!-- prettier-ignore-start -->

## What This Plugin Adds

Search is an **Available**, **Schema-owning** Capell package in the **Capell Search & SEO** product group. It ships as `capell-app/search` and extends these surfaces: admin, frontend, console.

Search adds public search, autocomplete, facets, synonyms, promoted results, spelling correction, and consent-aware search analytics.

Visitors can search indexed site content from the public search surface. Admins configure search behaviour and inspect top, trending, clicked, and zero-result queries.

Evidence: [`src/Actions/RunSearchAction.php`](src/Actions/RunSearchAction.php), [`src/Actions/RunAutocompleteSearchAction.php`](src/Actions/RunAutocompleteSearchAction.php), [`src/Actions/BuildSearchFacetGroupsAction.php`](src/Actions/BuildSearchFacetGroupsAction.php), [`tests/Feature/Http/SearchControllerTest.php`](tests/Feature/Http/SearchControllerTest.php), [`routes/web.php`](routes/web.php), [`src/Filament/Pages/SearchSettingsPage.php`](src/Filament/Pages/SearchSettingsPage.php), [`src/Actions/BuildTopSearchesQueryAction.php`](src/Actions/BuildTopSearchesQueryAction.php), [`src/Actions/BuildZeroResultSearchesQueryAction.php`](src/Actions/BuildZeroResultSearchesQueryAction.php).

Status details:

- Status: Available
- Tier: premium
- Bundle: search-seo
- Composer package: `capell-app/search`
- Namespace: `Capell\Search`
- Theme key: not applicable

## Why It Matters

**For developers:** The searchable-source registry and Search contract keep indexing backends replaceable while Actions normalize, sanitize, enhance, and record queries.

**For teams:** Teams can help visitors find content and use failed or repeated searches to decide which synonyms, promoted results, or content gaps to address.

Evidence: [`src/Support/SearchableSourceRegistry.php`](src/Support/SearchableSourceRegistry.php), [`src/Contracts/Search.php`](src/Contracts/Search.php), [`src/Actions/SanitizeSearchResultAction.php`](src/Actions/SanitizeSearchResultAction.php), [`tests/Feature/Providers/SearchServiceProviderTest.php`](tests/Feature/Providers/SearchServiceProviderTest.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`src/Actions/CreateSynonymFromZeroResultSearchAction.php`](src/Actions/CreateSynonymFromZeroResultSearchAction.php), [`src/Actions/CreatePromotedResultFromZeroResultSearchAction.php`](src/Actions/CreatePromotedResultFromZeroResultSearchAction.php), [`tests/Feature/Actions/ZeroResultCurationActionsTest.php`](tests/Feature/Actions/ZeroResultCurationActionsTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Top searches widget](docs/screenshots/top-searches-widget.png)

![Annotated search curation settings](docs/screenshots/search-curation-annotated.png)

- Frontend search results page (frontend, optional).
- Header search field (frontend, optional).
- Top searches widget (admin, required).
- Trending searches widget (admin, optional).
- Zero-result searches widget (admin, optional).
- Site search settings screen (admin, optional).
- Annotated search curation settings (admin, required).

## Technical Shape

- Service providers: `Capell\Search\Providers\SearchServiceProvider`, `Capell\Search\Providers\AdminServiceProvider`.
- Config files: `packages/search/config/capell-search.php`.
- Migrations: `packages/search/database/migrations/2026_05_10_190868_01_create_search_logs_table.php`, `packages/search/database/migrations/2026_05_21_000002_add_fulltext_index_to_search_database_table.php`, `packages/search/database/migrations/2026_07_12_000001_encrypt_search_log_pii.php`.
- Settings migrations: `packages/search/database/settings/2026_05_10_190869_01_add_search_settings.php`.
- Settings classes: `SearchSettings`.
- Models: `SearchLog`.
- Filament classes: `SearchSettingsPage`, `SearchDashboardSettingsContributor`, `SearchSettingsSchema`, `BuildsSearchInsightsWindow`, `SearchOverviewStatsFilamentWidget`, `TopSearchesFilamentWidget`, `TrendingSearchesFilamentWidget`, `ZeroResultSearchesFilamentWidget`.
- Route files: `packages/search/routes/web.php`.
- Extension contracts: `Search`.
- Actions: `ApplySearchResultEnhancementsAction`, `BuildAutocompleteQuerySuggestionsAction`, `BuildSearchFacetGroupsAction`, `BuildSearchPageViewDataAction`, `BuildTopClickedResultsQueryAction`, `BuildTopSearchesQueryAction`, `BuildTrendingSearchesQueryAction`, `BuildZeroResultSearchesQueryAction`, `CanCollectSearchAnalyticsAction`, `CreatePromotedResultFromZeroResultSearchAction`, `CreateSearchVisitorIdentityAction`, `CreateSynonymFromZeroResultSearchAction`, `and 20 more`.
- Data objects: `AutocompleteQuerySuggestionData`, `AutocompleteSearchResponseData`, `AutocompleteSearchResultData`, `PromotedSearchResultData`, `SearchFacetGroupData`, `SearchFacetOptionData`, `SearchFilterData`, `SearchInsightsWindowData`, `SearchPageViewData`, `SearchQueryMetadataData`, `SearchRequestData`, `SearchResultData`, `and 3 more`.
- Command signatures: `search:flush`, `search:index`, `search:purge`.
- Manifest action API: `applySearchResultEnhancements: Capell\Search\Actions\ApplySearchResultEnhancementsAction`, `buildTopClickedResultsQuery: Capell\Search\Actions\BuildTopClickedResultsQueryAction`, `buildTopSearchesQuery: Capell\Search\Actions\BuildTopSearchesQueryAction`, `buildTrendingSearchesQuery: Capell\Search\Actions\BuildTrendingSearchesQueryAction`, `buildZeroResultSearchesQuery: Capell\Search\Actions\BuildZeroResultSearchesQueryAction`, `createPromotedResultFromZeroResultSearch: Capell\Search\Actions\CreatePromotedResultFromZeroResultSearchAction`, `createSynonymFromZeroResultSearch: Capell\Search\Actions\CreateSynonymFromZeroResultSearchAction`, `flushScoutSearchSources: Capell\Search\Actions\FlushScoutSearchSourcesAction`, `indexScoutSearchSources: Capell\Search\Actions\IndexScoutSearchSourcesAction`, `install: Capell\Search\Actions\InstallSearchPackageAction`, `normalizeSearchQuery: Capell\Search\Actions\NormalizeSearchQueryAction`, `purgeSearchLogs: Capell\Search\Actions\PurgeSearchLogsAction`, `and 5 more`.
- Scheduled commands: `search:purge (monthly)`.
- Console command classes: `FlushSearchCommand`, `IndexSearchCommand`, `PurgeSearchLogsCommand`.
- Manifest contributions: `admin-page: Capell\Search\Manifest\SearchSettingsPageContribution`, `console-command: Capell\Search\Manifest\SearchConsoleCommandsContribution`, `dashboard-widget: Capell\Search\Manifest\TopSearchesWidgetContribution`, `dashboard-widget: Capell\Search\Manifest\TrendingSearchesWidgetContribution`, `dashboard-widget: Capell\Search\Manifest\ZeroResultSearchesWidgetContribution`, `health-check: Capell\Search\Manifest\SearchHealthContribution`, `model: Capell\Search\Manifest\SearchLogModelContribution`, `overview-stat: Capell\Search\Manifest\SearchOverviewStatsContribution`, `route: Capell\Search\Manifest\SearchFrontendRouteContribution`, `scheduled-job: Capell\Search\Manifest\SearchLogPurgeScheduleContribution`, `setting: Capell\Search\Manifest\SearchSettingsContribution`.
- Health checks: `Capell\Search\Health\SearchHealthCheck`.
- Blade views: `packages/search/resources/views/components/facets.blade.php`, `packages/search/resources/views/components/form.blade.php`, `packages/search/resources/views/components/header/autocomplete-results.blade.php`, `packages/search/resources/views/components/header/search-dialog.blade.php`, `packages/search/resources/views/components/header/search-modal.blade.php`, `packages/search/resources/views/components/header/search-trigger.blade.php`, `packages/search/resources/views/components/results.blade.php`, `packages/search/resources/views/filament/widgets/search-overview-stats.blade.php`, `packages/search/resources/views/layouts/frontend.blade.php`, `packages/search/resources/views/pages/search.blade.php`.
- Cache tags: `search`.

## Data Model

- Required tables: `search_logs`.
- Models: `SearchLog`.
- Core record references in migrations: `sites via site_id`, `languages via language_id`.
- Migration files: `2026_05_10_190868_01_create_search_logs_table.php`, `2026_05_21_000002_add_fulltext_index_to_search_database_table.php`, `2026_07_12_000001_encrypt_search_log_pii.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare null-on-delete relationships; retention is scheduled through `search:purge` (monthly).

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Admin navigation: declares `admin-page: SearchSettingsPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: `dashboard-widget: TopSearchesWidgetContribution`, `dashboard-widget: TrendingSearchesWidgetContribution`, `dashboard-widget: ZeroResultSearchesWidgetContribution`, `overview-stat: SearchOverviewStatsContribution`.
- Permissions: none declared in `capell.json`.
- Public routes: loads `routes/web.php`; registers `SearchFrontendRouteContribution`.
- Database changes: package migrations are declared.
- Config: `config/capell-search.php`.
- Settings: `Capell\Search\Settings\SearchSettings`.
- Queues or schedules: scheduled commands `search:purge (monthly)`.
- Cache tags: `search`.
- Commands: `search:flush`, `search:index`, `search:purge`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-search.php`, `Capell\Search\Settings\SearchSettings`.
- Review middleware, throttling, signatures, and public-output safety in `routes/web.php` before exposing routes.
- Register the host scheduler so these declared commands run at their documented frequencies: `search:purge (monthly)`.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `search` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/search`.
2. Run the required setup: `php artisan migrate`.
3. Open the package admin page or resource and verify Search is available.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-search.php`](config/capell-search.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Seo Suite](../seo-suite/README.md), [Site Discovery](../site-discovery/README.md), [Url Manager](../url-manager/README.md).
- Focused tests: `vendor/bin/pest packages/search/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
