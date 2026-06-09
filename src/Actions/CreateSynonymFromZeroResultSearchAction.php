<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Settings\SearchSettings;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * @method static array<string, mixed> run(string $zeroResultQuery, string $targetQuery)
 */
final class CreateSynonymFromZeroResultSearchAction
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(string $zeroResultQuery, string $targetQuery): array
    {
        $query = NormalizeSearchQueryAction::run($zeroResultQuery);
        $target = NormalizeSearchQueryAction::run($targetQuery);

        if ($query === '' || $target === '' || $query === $target) {
            return $this->settings()->synonyms;
        }

        $settings = $this->settings();
        $synonyms = $settings->synonyms;
        $existing = $synonyms[$target] ?? [];
        $aliases = is_array($existing) ? $existing : [$existing];
        $aliases[] = $query;
        $synonyms[$target] = array_values(array_unique(array_filter(
            array_map(static fn (mixed $alias): string => is_scalar($alias) ? NormalizeSearchQueryAction::run((string) $alias) : '', $aliases),
            static fn (string $alias): bool => $alias !== '' && $alias !== $target,
        )));

        $settings->synonyms = $synonyms;
        $this->save($settings);

        return $settings->synonyms;
    }

    private function settings(): SearchSettings
    {
        return resolve(SearchSettings::class);
    }

    private function save(SearchSettings $settings): void
    {
        try {
            $settings->save();
        } catch (Throwable) {
            //
        }
    }
}
