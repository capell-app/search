<?php

declare(strict_types=1);

namespace Capell\Search\Support;

/**
 * Wraps query matches in <mark> inside already-escaped text without ever cutting an HTML entity
 * (a search for "amp" must not turn &amp; into &<mark>amp</mark>;).
 */
final class HighlightsEscapedText
{
    public static function apply(string $escapedText, string $query): string
    {
        $needle = htmlspecialchars(trim($query), ENT_QUOTES, 'UTF-8');

        if ($needle === '') {
            return $escapedText;
        }

        $segments = preg_split('/(&(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);)/i', $escapedText, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($segments === false) {
            return $escapedText;
        }

        $pattern = '/(' . preg_quote($needle, '/') . ')/i';

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 0) {
                $segments[$index] = (string) preg_replace($pattern, '<mark>$1</mark>', $segment);
            }
        }

        return implode('', $segments);
    }
}
