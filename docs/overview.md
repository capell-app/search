# Search

<!-- prettier-ignore-start -->

## What This Plugin Adds

Search is an **Available**, **Schema-owning** Capell package in the **Capell Search & SEO** product group. It ships as `capell-app/search` and extends these surfaces: admin, frontend, console.

Search gives every Capell site a fast, themeable search experience: a results page, header search field, and type-ahead autocomplete, backed by a pluggable driver contract (database, Site Discovery URL registry, or Laravel Scout/Meilisearch/Typesense). Curate results with synonyms, typo corrections, and promoted "best bet" answers, and tune ranking with source weighting plus exact-match, recency, and click-through boosts. The admin dashboard surfaces top searches, trending terms, and - critically - zero-result queries, so editors see exactly where content is missing. Privacy-first by default: visitor identifiers are hashed and query logs auto-purge on a configurable retention window.

After install, admins get package-owned management surfaces and public users may see package-owned frontend output or routes.

Status details:

- Status: Available
- Tier: premium
- Bundle: search-seo
- Composer package: `capell-app/search`
- Namespace: `Capell\Search`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, models, Laravel routes, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Production-grade site search for Capell - relevance-tuned results with synonyms, typo tolerance, and curated promotions, plus admin insights into what visitors search for and where you have no answers.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Frontend search results page (frontend, required).
- Header search field (frontend, required).
- Top searches widget (admin, required).
- Trending searches widget (admin, required).
- Zero-result searches widget (admin, required).
- Site search settings screen (admin, required).
- Annotated search curation settings (admin, required).

## Technical Shape

- Service providers: `Capell\Search\Providers\SearchServiceProvider`, `Capell\Search\Providers\AdminServiceProvider`.
- Config files: `packages/search/config/capell-search.php`.
- Migrations: `packages/search/database/migrations/2026_05_10_190868_01_create_search_logs_table.php`, `packages/search/database/migrations/2026_05_21_000002_add_fulltext_index_to_search_database_table.php`.
- Settings migrations: `packages/search/database/settings/2026_05_10_190869_01_add_search_settings.php`.
- Settings classes: `SearchSettings`.
- Models: `SearchLog`.
- Filament classes: `SearchSettingsPage`, `SearchDashboardSettingsContributor`, `SearchSettingsSchema`, `BuildsSearchInsightsWindow`, `SearchOverviewStatsWidget`, `TopSearchesWidget`, `TrendingSearchesWidget`, `ZeroResultSearchesWidget`.
- Route files: `packages/search/routes/web.php`.
- Actions: `ApplySearchResultEnhancementsAction`, `BuildAutocompleteQuerySuggestionsAction`, `BuildSearchFacetGroupsAction`, `BuildTopClickedResultsQueryAction`, `BuildTopSearchesQueryAction`, `BuildTrendingSearchesQueryAction`, `BuildZeroResultSearchesQueryAction`, `CreatePromotedResultFromZeroResultSearchAction`, `CreateSynonymFromZeroResultSearchAction`, `FlushScoutSearchSourcesAction`, `GenerateSearchClickTokenAction`, `IndexScoutSearchSourcesAction`, `and 14 more`.
- Data objects: `AutocompleteQuerySuggestionData`, `AutocompleteSearchResponseData`, `AutocompleteSearchResultData`, `PromotedSearchResultData`, `SearchFacetGroupData`, `SearchFacetOptionData`, `SearchFilterData`, `SearchInsightsWindowData`, `SearchQueryMetadataData`, `SearchRequestData`, `SearchResultData`, `SearchTermSummaryData`, `and 1 more`.
- Command signatures: `search:flush`, `search:index`, `search:purge`.
- Console command classes: `FlushSearchCommand`, `IndexSearchCommand`, `PurgeSearchLogsCommand`.
- Manifest contributions: `console-command: Capell\Search\Manifest\SearchConsoleCommandsContribution`, `dashboard-widget: Capell\Search\Manifest\TopSearchesWidgetContribution`, `dashboard-widget: Capell\Search\Manifest\TrendingSearchesWidgetContribution`, `dashboard-widget: Capell\Search\Manifest\ZeroResultSearchesWidgetContribution`, `health-check: Capell\Search\Manifest\SearchHealthContribution`, `model: Capell\Search\Manifest\SearchLogModelContribution`, `overview-stat: Capell\Search\Manifest\SearchOverviewStatsContribution`, `route: Capell\Search\Manifest\SearchFrontendRouteContribution`, `scheduled-job: Capell\Search\Manifest\SearchLogPurgeScheduleContribution`, `setting: Capell\Search\Manifest\SearchSettingsContribution`.
- Health checks: `Capell\Search\Health\SearchHealthCheck`.
- Blade views: `packages/search/resources/views/components/facets.blade.php`, `packages/search/resources/views/components/form.blade.php`, `packages/search/resources/views/components/header/autocomplete-results.blade.php`, `packages/search/resources/views/components/header/search-dialog.blade.php`, `packages/search/resources/views/components/header/search-modal.blade.php`, `packages/search/resources/views/components/header/search-trigger.blade.php`, `packages/search/resources/views/components/results.blade.php`, `packages/search/resources/views/filament/widgets/search-overview-stats.blade.php`, `packages/search/resources/views/layouts/frontend.blade.php`, `packages/search/resources/views/pages/search.blade.php`.
- Cache tags: `search`.

## Data Model

- Required tables: `search_logs`.
- Models: `SearchLog`.
- Migration files: `2026_05_10_190868_01_create_search_logs_table.php`, `2026_05_21_000002_add_fulltext_index_to_search_database_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: `search:purge` removes old `search_logs` rows using the configured retention window and is scheduled monthly.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: route files exist and must be reviewed before public enablement.
- Database changes: package migrations are declared.
- Settings: `Capell\Search\Settings\SearchSettings`.
- Queues or schedules: schedules `search:purge` monthly when the package is installed.
- Cache tags: `search`.
- Commands: `search:flush`, `search:index`, `search:purge`.

## Common Pitfalls

- Run migrations before opening package resources or public routes.
- Configure package settings before testing production-like workflows.
- Review route middleware, throttling, signed URLs, and public-output safety before exposing routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Background work does not run | Queue worker or scheduled command is not active | Check package jobs, commands, and host scheduler configuration | Start the queue or scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/search`.
2. Run the host application's migration flow, including this package's migrations and settings migration.
3. Open the related Capell admin surface and verify Search appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Seo Suite](../../seo-suite/README.md), [Site Discovery](../../site-discovery/README.md), [Url Manager](../../url-manager/README.md).
- Focused tests: `vendor/bin/pest packages/search/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
