---
name: capell-site-search-development
description: Use when editing Capell Site Search drivers, frontend search, logging, or analytics.
---

# Capell Site Search

Frontend search route, search drivers, result click tracking, query logs, and admin analytics.

## Look

- `packages/site-search/src`
- `packages/site-search/docs`
- `packages/site-search/README.md`

## Rules

- Keep drivers behind contracts; do not hard-code one search backend.
- Query logging must respect settings and privacy expectations.
- Frontend search should stay cache-safe and site-scoped.
- Run `vendor/bin/pest packages/site-search/tests`.
