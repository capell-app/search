<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\DiscoveryFoundation\Actions\ReplacePhraseAction;
use Capell\DiscoveryFoundation\Actions\ResolveTypoCorrectionAction;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<string> run(string $normalizedQuery)
 */
final class ResolveExpandedSearchQueriesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return list<string>
     */
    public function handle(string $normalizedQuery): array
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($normalizedQuery);

        if ($normalizedQuery === '') {
            return [];
        }

        $queries = [$normalizedQuery];

        foreach ($this->synonymGroups() as $synonymGroup) {
            foreach ($synonymGroup as $sourcePhrase) {
                if (! $this->queryContainsPhrase($normalizedQuery, $sourcePhrase)) {
                    continue;
                }

                foreach ($synonymGroup as $replacementPhrase) {
                    if ($replacementPhrase === $sourcePhrase) {
                        continue;
                    }

                    $expandedQuery = $this->replacePhrase($normalizedQuery, $sourcePhrase, $replacementPhrase);

                    if ($expandedQuery !== '') {
                        $queries[] = $expandedQuery;
                    }
                }
            }
        }

        foreach ($this->typoCorrectedQueries($normalizedQuery) as $typoCorrectedQuery) {
            $queries[] = $typoCorrectedQuery;
        }

        return array_values(array_unique($queries));
    }

    /**
     * @return list<list<string>>
     */
    private function synonymGroups(): array
    {
        $configuredSynonyms = ResolveSearchSettingAction::run('synonyms', 'capell-search.synonyms', []);

        if (! is_array($configuredSynonyms)) {
            return [];
        }

        $groups = [];

        foreach ($configuredSynonyms as $termKey => $aliases) {
            $phrases = [];

            if (is_string($termKey)) {
                $phrases[] = NormalizeSearchQueryAction::run($termKey);
            }

            if (is_string($aliases)) {
                $phrases[] = NormalizeSearchQueryAction::run($aliases);
            }

            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    if (is_string($alias) || is_numeric($alias)) {
                        $phrases[] = NormalizeSearchQueryAction::run((string) $alias);
                    }
                }
            }

            $phrases = array_values(array_filter(
                array_unique($phrases),
                static fn (string $phrase): bool => $phrase !== '',
            ));

            if (count($phrases) > 1) {
                $groups[] = $phrases;
            }
        }

        return $groups;
    }

    private function queryContainsPhrase(string $query, string $phrase): bool
    {
        return preg_match($this->phrasePattern($phrase), $query) === 1;
    }

    private function replacePhrase(string $query, string $sourcePhrase, string $replacementPhrase): string
    {
        return ReplacePhraseAction::run($query, $sourcePhrase, $replacementPhrase);
    }

    private function phrasePattern(string $phrase): string
    {
        return '/(?<![\pL\pN])' . preg_quote($phrase, '/') . '(?![\pL\pN])/u';
    }

    /**
     * @return list<string>
     */
    private function typoCorrectedQueries(string $query): array
    {
        return array_values(array_filter(array_unique([
            ...$this->explicitTypoCorrections($query),
            ...$this->dictionaryTypoCorrections($query),
        ])));
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

            if (! $this->queryContainsPhrase($query, $misspelling)) {
                continue;
            }

            $queries[] = $this->replacePhrase($query, $misspelling, $correction);
        }

        return $queries;
    }

    /**
     * @return list<string>
     */
    private function dictionaryTypoCorrections(string $query): array
    {
        $terms = $this->typoTerms();

        if ($terms === []) {
            return [];
        }

        $corrected = ResolveTypoCorrectionAction::run($query, $terms, $this->typoMaxDistance());

        return $corrected === null ? [] : [$corrected];
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
}
