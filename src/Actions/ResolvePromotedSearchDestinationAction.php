<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\PromotedSearchDestinationData;
use Capell\Search\Data\SearchResultData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static PromotedSearchDestinationData run(string $url)
 */
final class ResolvePromotedSearchDestinationAction
{
    use AsFake;
    use AsObject;

    public function handle(string $url): PromotedSearchDestinationData
    {
        $result = SanitizeSearchResultAction::run(new SearchResultData(title: '', url: $url, excerpt: ''));

        return new PromotedSearchDestinationData(
            publicUrl: $result?->url,
            changed: $result !== null && $result->url !== $url,
        );
    }
}
