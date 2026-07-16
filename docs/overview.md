# Search

<!-- prettier-ignore-start -->

## What it does for you

Search powers the public results page, header search field, and autocomplete. It can search the content sources enabled for the site, then apply curation rules so visitors find the most useful result even when they use alternate wording or make a small typo.

## Configure search

Open **Extensions > Search > Settings**. Use the first section to:

- enable or disable public search and the header search field;
- choose the search driver, results-per-page limit, minimum query length, and the registered content sources to include; and
- decide whether to record query logs, how long to retain them, and whether visitor data is hashed.

Keep visitor hashing enabled unless your privacy policy and developer explicitly require a different choice. Source toggles appear only for search sources registered by installed packages.

## Curate difficult queries

Use the **Curation** section to improve searches without changing the source content first:

- Add a **synonym** with a canonical term and its aliases; for example, make `tee` also find `T-shirt`.
- Add a direct typo correction when a common misspelling should always map to a particular term. The optional typo terms and maximum distance control the broader typo matching.
- Add a **promoted result** when a query should lead with a specific title and URL. Supply the queries it answers, an optional excerpt/type, and a score; higher scores rank ahead of normal matches.

## Use dashboard feedback

Enabled Search dashboard widgets show top searches, trending searches, and zero-result searches for their configured insights window. Review zero-result terms regularly: add the missing content when appropriate, otherwise create a synonym or promoted result that directs the visitor to the best existing answer.

## Good to know

- Search settings change how future public queries are handled; they do not edit the underlying content.
- Query logging is the source of the search-insight widgets. If logging is disabled, the widgets have no new query activity to report.
- Indexing, flushing a Scout-backed index, and manually purging logs are developer or operations commands, not normal editor actions.

---

For how to use Search, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
