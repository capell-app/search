<?php

declare(strict_types=1);

namespace Capell\Search\Integrations\Agent;

use Capell\Core\Contracts\Agent\AgentPageSearch;
use Capell\Core\Data\Agent\AgentSearchResultData;
use Capell\Search\Actions\SanitizeSearchResultAction;
use Capell\Search\Contracts\Search;
use Capell\Search\Data\SearchFilterData;
use Capell\Search\Data\SearchResultData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Adapts the package search contract to Core's anonymous agent read boundary.
 *
 * The underlying search drivers own publication and site/language scoping. The
 * adapter additionally restricts the source type and projects only the three
 * fields that Core permits in the public agent response.
 */
final readonly class AgentPageSearchAdapter implements AgentPageSearch
{
    public function __construct(
        private Search $search,
        private int $perPage = 10,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AgentSearchResultData>
     */
    public function search(string $query, int $siteId, ?int $languageId, int $page = 1): LengthAwarePaginator
    {
        $results = $this->search->search(
            query: $query,
            perPage: $this->perPage,
            page: $page,
            siteId: $siteId,
            languageId: $languageId,
            filters: new SearchFilterData(types: ['page']),
        );

        /** @var list<AgentSearchResultData> $items */
        $items = [];

        foreach ($results->items() as $result) {
            if (! $result instanceof SearchResultData || $result->type !== 'page') {
                continue;
            }

            $safeResult = SanitizeSearchResultAction::run($result);

            if (! $safeResult instanceof SearchResultData) {
                continue;
            }

            $items[] = new AgentSearchResultData(
                url: $safeResult->url,
                title: $this->plainText($safeResult->title),
                snippet: $this->plainText($safeResult->excerpt),
            );
        }

        return new Paginator(
            items: $items,
            total: $results->total(),
            perPage: $results->perPage(),
            currentPage: $results->currentPage(),
            options: [
                'path' => $results->path(),
                'pageName' => $results instanceof Paginator ? $results->getPageName() : 'page',
            ],
        );
    }

    private function plainText(string $value): string
    {
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
