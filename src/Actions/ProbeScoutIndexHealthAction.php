<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ProbeScoutIndexHealthAction
{
    /**
     * @param  array{model: class-string<Model>, query: string, expected_model: array{column: string, value: int|string}, index?: string|null, database_count_method?: string}  $probe
     * @return array{database_count: int, index_count: int, sample_matched: bool}
     */
    public function handle(array $probe): array
    {
        $modelClass = $probe['model'];
        $search = [$modelClass, 'search'];

        if (! is_callable($search)) {
            throw new RuntimeException(sprintf('%s must provide the Scout search method.', $modelClass));
        }

        $indexCountBuilder = $search('*');
        $sampleBuilder = $search($probe['query']);

        if (! is_object($indexCountBuilder)
            || ! method_exists($indexCountBuilder, 'within')
            || ! method_exists($indexCountBuilder, 'raw')
            || ! is_object($sampleBuilder)
            || ! method_exists($sampleBuilder, 'within')
            || ! method_exists($sampleBuilder, 'get')) {
            throw new RuntimeException(sprintf('%s did not return compatible Scout builders.', $modelClass));
        }
        $index = $probe['index'] ?? null;

        if (is_string($index) && $index !== '') {
            $indexCountBuilder->within($index);
            $sampleBuilder->within($index);
        }

        $rawIndexResult = $indexCountBuilder->raw();
        $indexCount = $rawIndexResult['found'] ?? null;

        if (! is_int($indexCount) && ! (is_string($indexCount) && ctype_digit($indexCount))) {
            throw new RuntimeException('The Typesense index response did not contain a valid document count.');
        }

        $expectedModel = $modelClass::query()
            ->where($probe['expected_model']['column'], $probe['expected_model']['value'])
            ->first();

        if (! $expectedModel instanceof Model || ! method_exists($expectedModel, 'getScoutKey')) {
            throw new RuntimeException('The configured sample model could not be resolved from the database.');
        }

        $expectedModelId = (string) $expectedModel->getScoutKey();
        $sampleMatched = $sampleBuilder->get()->contains(
            static fn (object $result): bool => method_exists($result, 'getScoutKey')
                && (string) $result->getScoutKey() === $expectedModelId,
        );

        return [
            'database_count' => $this->databaseCount($modelClass, $probe['database_count_method'] ?? null),
            'index_count' => (int) $indexCount,
            'sample_matched' => $sampleMatched,
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function databaseCount(string $modelClass, ?string $method): int
    {
        if ($method === null || $method === '') {
            return $modelClass::query()->count();
        }

        if (! is_callable([$modelClass, $method])) {
            throw new RuntimeException(sprintf('%s::%s() is not a callable database count method.', $modelClass, $method));
        }

        $count = $modelClass::$method();

        if (! is_int($count) || $count < 0) {
            throw new RuntimeException(sprintf('%s::%s() must return a non-negative integer.', $modelClass, $method));
        }

        return $count;
    }
}
