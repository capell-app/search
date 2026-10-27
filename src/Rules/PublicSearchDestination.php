<?php

declare(strict_types=1);

namespace Capell\Search\Rules;

use Capell\Search\Actions\ResolvePromotedSearchDestinationAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

final class PublicSearchDestination implements ValidationRule
{
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ResolvePromotedSearchDestinationAction::run($value)->publicUrl === null) {
            $fail(__('capell-search::settings.promoted_url_rejected'));
        }
    }
}
