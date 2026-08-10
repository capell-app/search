<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\DiscoveryFoundation\Actions\ResolveTypoCorrectionAction;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?string run(string $query)
 */
final class ResolveCorrectedSearchQueryAction
{
    use AsFake;
    use AsObject;

    public function handle(string $query): ?string
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($query);

        if ($normalizedQuery === '') {
            return null;
        }

        foreach ($this->explicitTypoCorrections($normalizedQuery) as $correctedQuery) {
            return $correctedQuery;
        }

        return $this->dictionaryTypoCorrection($normalizedQuery);
    }

    /**
     * @return list<string>
     */
    private function explicitTypoCorrections(string $query): array
    {
        $configuredCorrections = ResolveSearchSettingAction::run('typo_corrections', 'capell-search.typo_corrections', []);

        if (! is_array($configuredCorrections)) {
            return [];
        }

        $queries = [];

        foreach ($configuredCorrections as $misspelling => $correction) {
            if (! is_string($misspelling)) {
                continue;
            }

            if (! is_string($correction) && ! is_numeric($correction)) {
                continue;
            }

            $misspelling = NormalizeSearchQueryAction::run($misspelling);
            $correction = NormalizeSearchQueryAction::run((string) $correction);
            if ($misspelling === '') {
                continue;
            }

            if ($correction === '') {
                continue;
            }

            if (preg_match($this->phrasePattern($misspelling), $query) !== 1) {
                continue;
            }

            $correctedQuery = preg_replace($this->phrasePattern($misspelling), $correction, $query);

            if (is_string($correctedQuery)) {
                $queries[] = NormalizeSearchQueryAction::run($correctedQuery);
            }
        }

        return array_values(array_filter(array_unique($queries)));
    }

    private function dictionaryTypoCorrection(string $query): ?string
    {
        $terms = $this->typoTerms();

        if ($terms === []) {
            return null;
        }

        return ResolveTypoCorrectionAction::run($query, $terms, $this->typoMaxDistance());
    }

    /**
     * @return list<string>
     */
    private function typoTerms(): array
    {
        $configuredTerms = ResolveSearchSettingAction::run('typo_terms', 'capell-search.typo_terms', []);

        if (! is_array($configuredTerms)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (string|int|float $term): string => NormalizeSearchQueryAction::run((string) $term),
            array_filter(
                $configuredTerms,
                static fn (mixed $term): bool => is_string($term) || is_numeric($term),
            ),
        )), static fn (string $term): bool => $term !== ''));
    }

    private function typoMaxDistance(): int
    {
        $configuredDistance = ResolveSearchSettingAction::run('typo_max_distance', 'capell-search.typo_max_distance', 1);

        return is_numeric($configuredDistance) ? max(0, min(3, (int) $configuredDistance)) : 1;
    }

    private function phrasePattern(string $phrase): string
    {
        return '/(?<![\pL\pN])' . preg_quote($phrase, '/') . '(?![\pL\pN])/u';
    }
}
