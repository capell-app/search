<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static string run(string $type, array<string, string>|null $configuredLabels = null)
 */
final class ResolveSearchResultTypeLabelAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, string>|null  $configuredLabels
     */
    public function handle(string $type, ?array $configuredLabels = null): string
    {
        $labels = $configuredLabels ?? config('capell-search.type_labels', []);

        if (is_array($labels) && is_string($labels[$type] ?? null) && $labels[$type] !== '') {
            return $labels[$type];
        }

        $translationKey = 'capell-search::types.' . str_replace('-', '_', $type);
        $translatedLabel = __($translationKey);

        if (is_string($translatedLabel) && $translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return str($type)->replace(['_', '-'], ' ')->headline()->toString();
    }
}
