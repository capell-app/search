<?php

declare(strict_types=1);

namespace Capell\Search\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Removes personal identifiers a visitor typed into the search box before the query is kept in the search log:
 * email addresses (including accented and internationalised ones) and long digit runs that look like phone or
 * card numbers. Ordinary queries, including short numbers and years, pass through unchanged.
 *
 * @method static string run(string $query)
 */
final class RedactSearchQueryAction
{
    use AsFake;
    use AsObject;

    public const string EMAIL_PLACEHOLDER = '[email]';

    public const string NUMBER_PLACEHOLDER = '[number]';

    private const string EMAIL_PATTERN = '/[\p{L}\p{N}._%+\-]+@(?:[\p{L}\p{N}\-]+\.)+[\p{L}\p{N}\-]{2,}/u';

    /** Seven or more digits, optionally separated by single spaces, dots, dashes or brackets, not part of a longer word. */
    private const string NUMBER_PATTERN = '/(?<![\p{L}\p{N}])\+?\(?\d(?:[\s().\-]{0,2}\d){6,}(?![\p{L}\p{N}])/u';

    public function handle(string $query): string
    {
        $redacted = preg_replace(self::EMAIL_PATTERN, self::EMAIL_PLACEHOLDER, $query);
        $redacted = preg_replace(self::NUMBER_PATTERN, self::NUMBER_PLACEHOLDER, is_string($redacted) ? $redacted : $query);

        // An invalid-UTF-8 query makes preg return null: keep nothing rather than the raw text.
        return is_string($redacted) ? $redacted : '';
    }
}
