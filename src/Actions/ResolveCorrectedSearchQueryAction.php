<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveCorrectedSearchQueryAction
{
    use AsAction;

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
        $configuredCorrections = config('capell-search.typo_corrections', []);

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

            if ($misspelling === '' || $correction === '') {
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

        $maxDistance = $this->typoMaxDistance();
        $tokens = preg_split('/\s+/', $query) ?: [];
        $correctedTokens = [];
        $changed = false;

        foreach ($tokens as $token) {
            if (! is_string($token) || mb_strlen($token) < 4) {
                $correctedTokens[] = (string) $token;

                continue;
            }

            $correction = $this->nearestTerm($token, $terms, $maxDistance);

            if ($correction !== null && $correction !== $token) {
                $correctedTokens[] = $correction;
                $changed = true;

                continue;
            }

            $correctedTokens[] = $token;
        }

        return $changed ? NormalizeSearchQueryAction::run(implode(' ', $correctedTokens)) : null;
    }

    /**
     * @return list<string>
     */
    private function typoTerms(): array
    {
        $configuredTerms = config('capell-search.typo_terms', []);

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
        $configuredDistance = config('capell-search.typo_max_distance', 1);

        return is_numeric($configuredDistance) ? max(0, min(3, (int) $configuredDistance)) : 1;
    }

    /**
     * @param  list<string>  $terms
     */
    private function nearestTerm(string $token, array $terms, int $maxDistance): ?string
    {
        $nearestTerm = null;
        $nearestDistance = $maxDistance + 1;

        foreach ($terms as $term) {
            if (abs(mb_strlen($term) - mb_strlen($token)) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($token, $term);

            if ($distance > $maxDistance || $distance >= $nearestDistance) {
                continue;
            }

            $nearestTerm = $term;
            $nearestDistance = $distance;
        }

        return $nearestTerm;
    }

    private function phrasePattern(string $phrase): string
    {
        return '/(?<![\pL\pN])' . preg_quote($phrase, '/') . '(?![\pL\pN])/u';
    }
}
