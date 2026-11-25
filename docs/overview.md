# Search

<!-- prettier-ignore-start -->

On your site, a visitor who types "tee" can land on your T-shirt page first, because you decide that on the Search settings page. Synonyms, typo fixes and promoted results are all set there.

The search button sits in your site header and opens a box that shows matching pages as the visitor types. A results page lists the rest with the matching words highlighted. The button appears only if your theme uses the header from the Navigation package. Turning off the Enable search setting makes search disappear from the site altogether, including the header button and the results page. To hide only the button, turn off Show header search.

Search reads the list of your public pages that Discovery Foundation keeps, so there's no separate search index to build and an edit shows on the next search. It matches the title, the address and the first 1000 characters of page text as plain text. Anything further down a long page isn't found.

Administrators get the top, trending and zero-result searches on the admin dashboard, all taken from the results page. You see what people looked for and didn't find.

## What it does for you

Search powers the public results page, header search field, and autocomplete. It can search the content sources enabled for the site, then apply curation rules so visitors find the most useful result even when they use alternate wording or make a small typo.

## Configure search

Open **Extensions > Search > Settings**. The page is protected by `View:SearchSettingsPage`; that permission grants access to the settings form and its save action. Use the first section to:

- show or hide the package's header search trigger;
- choose the search driver, results-per-page limit, minimum query length, and the registered content sources to include; and
- decide whether to record query logs, how long to retain them, and whether visitor data is hashed.

**Enable search** is the master switch: while it is off the header button, the results page, type-ahead and click tracking are all unavailable and return 404. **Show header search** hides only the header button and leaves the search page and type-ahead live. Use **Enable search** as the switch for taking public search offline.

## Choose the right source lifecycle

- **Built-in page index (recommended)** searches the site's currently indexable public URL records. It is the default and does not use the registered-source toggles.
- **Database full-text** searches the configured table and columns directly. It does not use the registered-source toggles and is suitable only when the public title, URL, excerpt, body, site, language, and publication status are available in that shape.
- **Laravel Scout (Typesense, Meilisearch, Algolia)** searches the enabled registered model sources. The source toggles apply here, but changing a toggle or switching drivers does not build an external index.

For Scout, operations must run `search:index` for the required source and keep that index current through Scout model observers or source-owned updates. `search:flush` removes the matching configured Scout source from its index and fails when no flushable source matches, so use it only as a deliberate maintenance operation. The package health check proves that the driver resolves; it does not contact Meilisearch or Typesense or prove index freshness.

## Curate difficult queries

Use the **Curation** section to improve searches without changing the source content first:

- Add a **synonym** with a canonical term and its aliases; for example, make `tee` also find `T-shirt`.
- Add a direct typo correction when a common misspelling should always map to a particular term. The optional typo terms and maximum distance control the broader typo matching.
- Add a **promoted result** when a query should lead with a specific title and URL. Supply the queries it answers, an optional excerpt/type, and a score; higher scores rank ahead of normal matches.

Promoted links are still passed through the public-result safety filter. Admin and control-panel path prefixes are rejected, non-HTTP schemes are rejected, and configured sensitive query parameters such as signatures, tokens, and previews are removed. Test each promoted result on the public search page after saving it.

## Use dashboard feedback

Enabled Search dashboard widgets show top searches, trending searches, and zero-result searches for their configured insights window. Review zero-result terms regularly: add the missing content when appropriate, otherwise create a synonym or promoted result that directs the visitor to the best existing answer.

The widgets are restricted to their configured admin or super-admin roles and dashboard visibility settings. Their records are scoped to the active dashboard site when a site context is available.

## Query logs, privacy, and retention

- Only a full results-page search creates a search log; autocomplete does not. The write is deferred until after the response and is skipped for a blank or too-short query, a request without a resolved site, or a request carrying `Sec-GPC: 1`, `DNT: 1`, or `X-Do-Not-Track: 1` while privacy-signal support is enabled.
- The canonical query and a clicked result path are encrypted. Hashes are retained for grouping and click ranking, alongside result count, site, language, and search time.
- Those hashes are keyed by `CAPELL_SEARCH_LOG_HASH_SECRET`, never by `APP_KEY`. Set it to a long random value (for example `openssl rand -base64 48`) before relying on search insights. While it is missing, no searches or clicks are recorded and the Search health check fails with the fix. Each row records a short identifier of the secret that keyed it (never the secret), so after setting or rotating the secret, the `search:rehash-logs` command re-keys retained rows from their encrypted values and the upgrade re-keys rows hashed under `APP_KEY`. It is safe to run again; rows whose values no longer decrypt are left unchanged and reported.
- With **Hash visitor logs** enabled, IP address and user-agent values are stored only as rotating, site-specific HMAC hashes keyed by `capell-search.visitor_hash_secret`, else the search log hash secret. Turning it off stores neither value; it does not store them in plaintext.
- Turning off **Record search logs** stops future query rows but does not delete existing rows. The configured retention window is enforced by the monthly `search:purge` schedule, so Laravel's scheduler must run in production. Changing the window does not purge immediately; operations can run `search:purge` for an immediate cleanup.

## Good to know

- Search settings change how future public queries are handled; they do not edit the underlying content.
- Query logging is the source of the search-insight widgets. If logging is disabled, the widgets have no new query activity to report.
- The installer publishes and runs the package migrations and publishes the header-search JavaScript asset. If logging or the header dialog fails after installation, verify the `search_logs` table and the published asset before changing curation settings.
- Indexing, flushing a Scout-backed index, and manually purging logs are developer or operations commands, not normal editor actions.

---

For how to use Search, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
