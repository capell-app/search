# Drivers And Logging

Search exposes a public frontend route and records first-party search behavior when logging is enabled. The search backend is behind `Capell\Search\Contracts\Search`.

## Config Keys

| Key                                                | Use                                                                                                               |
| -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `capell-search.enabled`                            | Enables frontend search.                                                                                          |
| `capell-search.show_header_search`                 | Registers the header search render hook.                                                                          |
| `capell-search.driver`                             | `database`, `site_discovery`, or `scout`; defaults to `site_discovery` unless `CAPELL_SITE_SEARCH_DRIVER` is set. |
| `capell-search.route_path`                         | Public route path, default `search`.                                                                              |
| `capell-search.results_per_page`                   | Default pagination size.                                                                                          |
| `capell-search.excerpt_length`                     | Generated excerpt length.                                                                                         |
| `capell-search.minimum_query_length`               | Rejects short queries.                                                                                            |
| `capell-search.record_search_logs`                 | Writes query and click logs.                                                                                      |
| `capell-search.hash_visitor_data`                  | Hashes visitor identifiers in logs.                                                                               |
| `capell-search.ranking.click_counts_cache_seconds` | Caches click-through aggregate counts used for result boosts; set to `0` to disable.                              |
| `capell-search.database.*`                         | Database driver table and column mapping.                                                                         |
| `capell-search.database.column_weights`            | Database driver fallback ranking weights for fields such as `title`, `excerpt`, and `body`.                       |
| `capell-search.scout.*`                            | Scout model and column mapping.                                                                                   |
| `capell-search.logs.retention_days`                | Retention window for purge behavior.                                                                              |

The Site Discovery driver is the safe default because it searches Capell's canonical public URL registry instead of assuming page content lives in flat database columns. The database driver expects a flat searchable source. Do not point it at a table whose title, body, URL, or type values live only in nested JSON unless the configured columns can be queried directly. For standard Capell pages, use Site Discovery for URL/title search, a flattened index table/view, a custom `Search` implementation, or Scout. When the database driver falls back to `LIKE`, `database.column_weights` controls the generated `search_score`, so a title match can outrank repeated body matches.

## Scout Sources And Freshness

Scout sources are registered through `capell-search.searchables`. Each source must point at an Eloquent model that owns its public `toSearchableArray()` payload and uses Scout's indexing behavior. Search can import or flush configured sources, but source packages remain responsible for model observers, queue configuration, and payload freshness.

```php
'searchables' => [
    'articles' => [
        'label' => 'Articles',
        'model' => App\Models\Article::class,
        'type' => 'article',
        'enabled' => true,
        'weight' => 1.25,
        'index' => 'articles',
    ],
],
```

Use the package commands for explicit maintenance:

```bash
vendor/bin/testbench search:index --source=articles --chunk=500
vendor/bin/testbench search:flush --source=articles
```

When a source model changes public visibility, site/language assignment, URL, title, or body payload, that source should either rely on Scout's model observer behavior or dispatch its own searchable/unsearchable update. The Search package's health check proves the configured driver resolves and query logs are writable; it does not contact remote Meili/Typesense services or prove external index freshness.

## Swap the Search Driver

Bind the contract when a package needs a custom backend.

```php
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class DemoSearchDriver implements Search
{
    public function search(
        string $query,
        int $perPage = 10,
        int $page = 1,
        ?int $siteId = null,
        ?int $languageId = null,
        ?SearchFilterData $filters = null,
    ): LengthAwarePaginator {
        $results = collect([
            new SearchResultData(
                title: 'About Capell',
                url: '/about',
                excerpt: $this->highlight('About Capell CMS', $query),
                type: 'page',
                score: 1.0,
            ),
        ]);

        return new Paginator($results, $results->count(), $perPage, $page);
    }

    public function highlight(string $text, string $query): string
    {
        $escapedText = e($text);
        $query = trim($query);

        if ($query === '') {
            return $escapedText;
        }

        return preg_replace('/(' . preg_quote(e($query), '/') . ')/i', '<mark>$1</mark>', $escapedText)
            ?? $escapedText;
    }
}

$this->app->singleton(Search::class, DemoSearchDriver::class);
```

`highlight()` returns HTML rendered on public pages, so every custom driver must escape the
entire input text before adding trusted `<mark>` tags. Do not return raw model content or
admin/editor metadata from a search driver.

## Logging

Use package actions instead of writing `SearchLog` rows directly:

- `RecordSearchAction`
- `RecordSearchResultClickAction`
- `PurgeSearchLogsAction`

Hash visitor data unless a product requirement says otherwise.

Click-through counts are cached briefly on the frontend hot path and scoped to the active site when search receives a site context. Recording a result click through `RecordSearchResultClickAction` invalidates the all-site cache and the clicked log's site-specific cache so boosted result ordering catches up without waiting for the TTL.

## Verification

```bash
vendor/bin/pest packages/search/tests --configuration=phpunit.xml
```
