<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final class BusinessCalendar
{
    public static function timezone(): string
    {
        return (string) config('reconflow.display_timezone');
    }

    public static function windowClosesAt(string $businessDate): CarbonImmutable
    {
        return CarbonImmutable::parse($businessDate, self::timezone())->startOfDay()->addDay()->addHours((int) config('reconflow.payments_window_grace_hours'));
    }

    public static function isClosed(string $businessDate, ?CarbonImmutable $now = null): bool
    {
        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo(self::windowClosesAt($businessDate));
    }

    public static function latestClosedDate(?CarbonImmutable $now = null): string
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(self::timezone());
        $candidate = $now->startOfDay()->subDay();

        return self::isClosed($candidate->toDateString(), $now) ? $candidate->toDateString() : $candidate->subDay()->toDateString();
    }
}
