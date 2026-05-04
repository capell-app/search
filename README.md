# Site Search

Status: **Available, schema-owning** · Kind: **package** · Tier: **premium** · Bundle: **search-seo** · Contexts: **admin, frontend, console** · Product group: **Capell Search & SEO**

## What This Plugin Adds

Site Search adds a frontend search route, configurable search drivers, result click tracking, query logging, and admin search analytics.

- Frontend search controller route.
- Database and Scout search drivers.
- Header search render hook.
- Search analytics widgets.
- Settings schema and dashboard contributor.
- Actions for search, normalization, logging, click tracking, and purging logs.

## Why It Matters

**For developers:** Uses a SiteSearch contract and driver classes so search can start with database queries and move to Scout without changing the frontend surface.

**For teams:** Lets visitors search site content and lets operators review what people searched for, including zero-result terms.

## Screens And Workflow

Screenshots are generated from [docs/screenshots.json](docs/screenshots.json) during package deployment.

- Frontend search results page.
- Header search field.
- Top searches widget.
- Trending searches widget.
- Zero-result searches widget.
- Site search settings screen.

## Technical Shape

- SiteSearchServiceProvider and AdminServiceProvider register the package.
- Config file: capell-site-search.php.
- Route: GET search by default, named capell-frontend.search.
- Model: SiteSearchLog.
- Drivers: DatabaseSiteSearch and ScoutSiteSearch.
- Command: PurgeSiteSearchLogsCommand.

## Data Model

- site_search_logs stores site, language, query, normalized query, result count, clicked URL, and searched_at.
- Logs connect to sites and languages.
- Retention defaults to 180 days in config.

## Install Impact

- Adds site_search_logs table and settings migration.
- Adds frontend search route.
- Adds optional header search render hook.
- Adds dashboard analytics widgets.
- Adds config keys for driver, route, result count, excerpts, logging, hashing, and retention.

## Commands

- `site-search:purge {--days= : Override retention days}` (packages/site-search/src/Console/Commands/PurgeSiteSearchLogsCommand.php)

## Admin And Access

- None proven in this package directory.

- Gate: SearchOverviewStatsWidget: `admin`, `super_admin`
- Gate: TopSearchesWidget: `admin`, `super_admin`
- Gate: TrendingSearchesWidget: `admin`, `super_admin`
- Gate: ZeroResultSearchesWidget: `admin`, `super_admin`

## Common Pitfalls

- Database driver config must point at searchable columns that exist.
- Minimum query length defaults to 2 characters.
- Disable logging or hashing according to privacy requirements.
- Run log purge if retention needs enforcement.

## Quick Start

1. Install the package with `composer require capell-app/site-search`.
2. Run the package migrations or the Capell package installer required by the host app.
3. Open the new admin or frontend surface and verify the result.

## Next Steps

- [docs/overview.md](docs/overview.md)
- [../analytics/README.md](../analytics/README.md)
- [../seo-tools/README.md](../seo-tools/README.md)
