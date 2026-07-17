# Using Search

This guide is for editors who tune site search and owners deciding what should be searchable. Every step uses the labels you see on screen.

## Using Search (editor how-to)

### How to choose what is searchable

1. Open **Extensions**, select **Search**, then open **Settings**.
2. Turn on each registered content source that should appear in public search.
3. Set the driver, logging, retention, and result behavior.
4. Save. Visitors can now find the enabled sources from the search box.

![A site owner configures search settings, logging, retention, and result behaviour.](screenshots/search-settings-screen.png)

### How to see what visitors search for

1. Open the main admin dashboard.
2. Enable the Search dashboard widgets if your dashboard settings hide them.
3. Review the most common searches, rising searches, and searches that returned nothing.
4. Use those signals to decide what content, synonyms, typo corrections, or promoted results to add.

The **Top searches** widget shows your most searched terms. **Trending searches** compares the current dashboard period with the preceding one, and **Zero result searches** highlights terms that need content or curation.

![An administrator reviews the top searched terms from query logs.](screenshots/top-searches-widget.png)

![An administrator reviews trending searches over the configured window.](screenshots/trending-searches-widget.png)

### How to add a synonym

1. Open **Extensions** → **Search** → **Settings**.
2. In **Search curation**, add a **Query** and its equivalent queries (for example "tee" and "T-shirt").
3. Save. Now either word finds the same results.

The same **Search curation** section also lets you correct common typos and promote a chosen answer for a query.

![A site owner sees how search curation controls query intent and promoted answers.](screenshots/search-curation-annotated.png)

### How to handle searches with no results

1. Review the **Zero result searches** dashboard widget.
2. Either add the missing content, or use **Search curation** to add a synonym, typo correction, or promoted result that leads visitors to existing content.

![An administrator identifies search terms with no matching content.](screenshots/zero-result-searches-widget.png)

## Rolling out Search (for owners)

### Turn on first

- **A sensible set of searchable content.** Make your key pages and products findable before fine-tuning.

### Add when needed

| Need                               | Enable                   |
| ---------------------------------- | ------------------------ |
| Different words for the same thing | **Synonyms**             |
| Understand visitor intent          | Search dashboard widgets |

### Don't enable yet

- Don't make everything searchable at once. Too much low-value content makes results noisy. Add as needed.

### Who does what

| Role       | First useful screen                              |
| ---------- | ------------------------------------------------ |
| Editor     | Search dashboard widgets and **Search curation** |
| Site owner | **Search settings**: decide what is searchable   |

## Troubleshooting for editors

| What you see                     | What it means                                               | What to do                                              |
| -------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------- |
| A search returns nothing         | The content is not enabled, or visitors use different words | Enable the source, then add content or a curation rule  |
| Results feel noisy or irrelevant | Too much low-value content is indexed                       | Narrow the enabled sources in **Search settings**       |
| A new page isn't found in search | The source has not refreshed its index                      | Ask a developer to check the source's indexing behavior |
