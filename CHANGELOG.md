# Changelog

All notable changes to `capell-app/search` will be documented in this file.

## Unreleased

- Added a configurable per-user/IP rate limiter to the full public search route, closing the expensive unthrottled search-and-log path.

### 2026-06-03

#### Changed

- Rewrote the marketplace summary, package description, and `composer.json` description to lead with the production search value proposition.
- Expanded `marketplace.screenshots` from a single extension card to the existing frontend search, header autocomplete, settings, top-searches, trending-searches, and zero-result insight captures.

#### Fixed

- Replaced the stubbed `SearchHealthCheck` with real diagnostics for the search log table, SearchLog model registration, and log-writing configuration readiness. Added pass and fail coverage.

### Earlier

- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.
