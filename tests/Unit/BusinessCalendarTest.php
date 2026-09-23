<?php

declare(strict_types=1);

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;

it('treats a business date as closed once its payment window ends at 06:00 the next day', function (): void {
    expect(BusinessCalendar::isClosed('2026-09-22', CarbonImmutable::parse('2026-09-23 05:59:59', 'Africa/Nairobi')))->toBeFalse()
        ->and(BusinessCalendar::isClosed('2026-09-22', CarbonImmutable::parse('2026-09-23 06:00:00', 'Africa/Nairobi')))->toBeTrue();
});

it('finds the latest closed business date', function (string $now, string $expected): void {
    expect(BusinessCalendar::latestClosedDate(CarbonImmutable::parse($now, 'Africa/Nairobi')))->toBe($expected);
})->with([
    ['2026-09-24 07:00:00', '2026-09-23'],
    ['2026-09-24 05:00:00', '2026-09-22'],
    ['2026-09-24 03:30:00', '2026-09-22'],
]);
