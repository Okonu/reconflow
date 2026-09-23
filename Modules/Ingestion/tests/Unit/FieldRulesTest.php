<?php

declare(strict_types=1);

use Modules\Ingestion\Support\Schema\FieldParser;

it('parses money to cents and rejects anything that is not a positive two-decimal amount', function (string $raw, ?string $value, ?string $error): void {
    $parsed = FieldParser::money($raw);
    expect($parsed->value)->toBe($value)->and($parsed->error)->toBe($error);
})->with([
    'integer' => ['77', '77.00', null],
    'two decimals' => ['589.25', '589.25', null],
    'trailing zeros' => ['77.000', '77.00', null],
    'excel float noise' => ['12.340000000001', '12.34', null],
    'three decimals' => ['12.345', null, 'Amount has more than 2 decimal places'],
    'negative' => ['-31.00', null, 'Amount must be greater than 0'],
    'zero' => ['0', null, 'Amount must be greater than 0'],
    'text' => ['abc', null, "Invalid amount 'abc'"],
    'thousands separator' => ['1,500.00', null, "Invalid amount '1,500.00'"],
]);

it('accepts only 2547XXXXXXXX phone numbers', function (string $raw, bool $ok): void {
    expect(FieldParser::phone($raw)->failed())->toBe(! $ok);
})->with([
    ['254700123456', true],
    ['07001234', false],
    ['0700123456', false],
    ['2547001234567', false],
]);

it('parses EAT timestamps to UTC and rejects impossible times', function (): void {
    expect(FieldParser::timestamp('2026-09-22 07:36:58', 'Africa/Nairobi')->value)->toBe('2026-09-22T04:36:58Z')
        ->and(FieldParser::timestamp('22/09/2026 25:61', 'Africa/Nairobi')->error)->toBe('Unparseable timestamp')
        ->and(FieldParser::timestamp('2026-02-30 10:00:00', 'Africa/Nairobi')->failed())->toBeTrue();
});

it('parses dates including midnight datetimes from spreadsheets', function (): void {
    expect(FieldParser::date('2026-09-22', 'business_date')->value)->toBe('2026-09-22')
        ->and(FieldParser::date('2026-09-22 00:00:00', 'business_date')->value)->toBe('2026-09-22')
        ->and(FieldParser::date('22-09-2026', 'business_date')->error)->toBe('Unparseable date in business_date');
});
