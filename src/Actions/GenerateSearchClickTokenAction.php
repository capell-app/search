<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Capell\Search\Data\SearchRequestData;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static ?string run(SearchRequestData $data)
 */
final class GenerateSearchClickTokenAction
{
    use AsAction;

    public function handle(SearchRequestData $data): ?string
    {
        $normalizedQuery = NormalizeSearchQueryAction::run($data->query);
        $minimumLength = $this->integerSetting(
            'minimum_query_length',
            'capell-search.minimum_query_length',
            2,
        );

        if ($normalizedQuery === '' || mb_strlen($normalizedQuery) < $minimumLength) {
            return null;
        }

        return Crypt::encryptString(json_encode([
            'query' => $normalizedQuery,
            'site_id' => $data->siteId,
            'language_id' => $data->languageId,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function integerSetting(string $settingKey, string $configKey, int $fallback): int
    {
        $value = ResolveSearchSettingAction::run($settingKey, $configKey, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
