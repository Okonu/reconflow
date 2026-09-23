<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support;

use DateInterval;
use DateTimeInterface;

final class CellValue
{
    public static function normalise(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format('H:i:s') === '00:00:00' && $value->format('u') === '000000'
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s'),
            $value instanceof DateInterval => $value->format('%H:%I:%S'),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_int($value) => (string) $value,
            is_float($value) => self::float($value),
            default => trim((string) $value),
        };
    }

    private static function float(float $value): string
    {
        $fixed = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

        return $fixed === '-0' ? '0' : $fixed;
    }
}
