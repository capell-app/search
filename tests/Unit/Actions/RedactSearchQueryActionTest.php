<?php

declare(strict_types=1);

use Capell\Search\Actions\RedactSearchQueryAction;

test('replaces email addresses, including accented and internationalised ones', function (string $query, string $expected): void {
    expect(RedactSearchQueryAction::run($query))->toBe($expected);
})->with([
    'plain' => ['invoice for jane.doe+news@example.com please', 'invoice for [email] please'],
    'accented local part' => ['contact josé.müller@exämple.de', 'contact [email]'],
    'internationalised domain' => ['用户@例子.广告', '[email]'],
    'only the address' => ['a@b.co', '[email]'],
]);

test('replaces phone and card like digit runs but keeps ordinary numbers', function (string $query, string $expected): void {
    expect(RedactSearchQueryAction::run($query))->toBe($expected);
})->with([
    'international phone' => ['call +44 20 7946 0958 now', 'call [number] now'],
    'bracketed phone' => ['(020) 7946-0958', '[number]'],
    'card number' => ['4111 1111 1111 1111', '[number]'],
    'long unbroken digits' => ['order 12345678901', 'order [number]'],
    'year' => ['annual report 2024', 'annual report 2024'],
    'route number' => ['route 66 timetable', 'route 66 timetable'],
    'short code' => ['form 1040 help 123456', 'form 1040 help 123456'],
    'digits inside a word' => ['sku ab1234567cd', 'sku ab1234567cd'],
]);

test('leaves ordinary queries untouched', function (): void {
    expect(RedactSearchQueryAction::run('where is the café opening hours'))->toBe('where is the café opening hours');
});

test('keeps nothing when the text is not valid UTF-8', function (): void {
    expect(RedactSearchQueryAction::run("bad \xB1 bytes"))->toBe('');
});
