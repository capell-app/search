<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchableSourceData;
use Capell\Search\Support\SearchableSourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class RegisterConfiguredSearchableSourcesAction
{
    use AsFake;
    use AsObject;

    public function handle(SearchableSourceRegistry $registry): void
    {
        $sources = config('capell-search.searchables', []);

        if (! is_array($sources)) {
            return;
        }

        foreach ($sources as $key => $source) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_array($source)) {
                continue;
            }

            $modelClass = $source['model'] ?? null;
            if (! is_string($modelClass)) {
                continue;
            }

            if (! is_a($modelClass, Model::class, true)) {
                continue;
            }

            $registry->register(new SearchableSourceData(
                key: $key,
                label: (string) ($source['label'] ?? $key),
                modelClass: $modelClass,
                type: (string) ($source['type'] ?? $key),
                enabledSettingKey: sprintf('sources.%s.enabled', $key),
                enabledByDefault: (bool) ($source['enabled'] ?? true),
                weight: (float) ($source['weight'] ?? 1.0),
                indexName: is_string($source['index'] ?? null) ? $source['index'] : null,
            ));
        }
    }
}
