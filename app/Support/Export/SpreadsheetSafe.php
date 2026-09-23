<?php

declare(strict_types=1);

namespace App\Support\Export;

final class SpreadsheetSafe
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function escape(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::TRIGGERS, true) ? "'".$value : $value;
    }

    public static function row(array $values): array
    {
        return array_map(self::escape(...), $values);
    }
}
