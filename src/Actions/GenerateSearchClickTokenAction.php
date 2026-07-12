<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchRequestData;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static ?string run(SearchRequestData $data, string $resultUrl)
 */
final class GenerateSearchClickTokenAction
{
    use AsAction;

    public function handle(SearchRequestData $data, string $resultUrl): ?string
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($data->query);
        $minimumLength = $this->integerSetting(
            'minimum_query_length',
            'capell-search.minimum_query_length',
            2,
        );

        $resultPath = parse_url($resultUrl, PHP_URL_PATH);

        if ($normalizedQuery === '' || mb_strlen($normalizedQuery) < $minimumLength
            || ! is_string($resultPath) || $resultPath === '') {
            return null;
        }

        return Crypt::encryptString(json_encode([
            'query_hash' => HashSearchRetentionValueAction::run($normalizedQuery),
            'site_id' => $data->siteId,
            'language_id' => $data->languageId,
            'result_path' => $resultPath,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function integerSetting(string $settingKey, string $configKey, int $fallback): int
    {
        $value = ResolveSearchSettingAction::run($settingKey, $configKey, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
