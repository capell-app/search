<?php

declare(strict_types=1);

namespace Capell\Search\Data;

use Capell\Search\Actions\ResolveSearchSettingAction;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * @param  class-string<Model>  $modelClass
 */
final class SearchableSourceData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $modelClass,
        public readonly string $type,
        public readonly ?string $enabledSettingKey = null,
        public readonly bool $enabledByDefault = true,
        public readonly float $weight = 1.0,
        public readonly ?string $indexName = null,
        public readonly ?Closure $urlResolver = null,
    ) {}

    public function enabled(): bool
    {
        if ($this->enabledSettingKey === null || $this->enabledSettingKey === '') {
            return $this->enabledByDefault;
        }

        return (bool) ResolveSearchSettingAction::run(
            $this->enabledSettingKey,
            'capell-search.' . $this->enabledSettingKey,
            $this->enabledByDefault,
        );
    }
}
