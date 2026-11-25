# Changelog

All notable changes to `capell-app/search` will be documented in this file.

## Unreleased

- Search log hashes no longer fall back to `APP_KEY`. Query, clicked-result and visitor hashes are keyed by `CAPELL_SEARCH_LOG_HASH_SECRET` (visitor hashes may use `visitor_hash_secret`); while it is missing nothing is recorded and the health check says how to fix it. **Upgrade:** set `CAPELL_SEARCH_LOG_HASH_SECRET` before upgrading, or search logging stops until it is set.
- Added `hash_key_id` to search logs, the `search:rehash-logs` command and an upgrade step that re-key retained hashes from their encrypted values after the secret is set or rotated.
- Legacy plain-text search logs are now redacted (email addresses, long numbers) as well as encrypted, and the health probe writes through the encrypted model.
- Added a configurable per-user/IP rate limiter to the full public search route, closing the expensive unthrottled search-and-log path.

### 2026-06-03

#### Changed

- Rewrote the marketplace summary, package description, and `composer.json` description to lead with the production search value proposition.
- Expanded `marketplace.screenshots` from a single extension card to the existing frontend search, header autocomplete, settings, top-searches, trending-searches, and zero-result insight captures.

#### Fixed

- Replaced the stubbed `SearchHealthCheck` with real diagnostics for the search log table, SearchLog model registration, and log-writing configuration readiness. Added pass and fail coverage.

### Earlier

- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.
