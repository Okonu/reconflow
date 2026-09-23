<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

final class FieldParser
{
    private const TIMESTAMP_FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    private const PHONE = '/^2547\d{8}$/';

    private const FLOAT_NOISE = '0.000000001';

    public static function money(string $value): ParsedField
    {
        try {
            $amount = BigDecimal::of($value);
        } catch (MathException) {
            return ParsedField::fail(Reason::invalidAmount($value));
        }
        if ($amount->isNegativeOrZero()) {
            return ParsedField::fail(Reason::amountNotPositive());
        }
        $cents = $amount->toScale(2, RoundingMode::HalfUp);
        if ($amount->minus($cents)->abs()->isGreaterThan(BigDecimal::of(self::FLOAT_NOISE))) {
            return ParsedField::fail(Reason::amountTooPrecise());
        }

        return ParsedField::ok((string) $cents);
    }

    public static function phone(string $value): ParsedField
    {
        return preg_match(self::PHONE, $value) === 1 ? ParsedField::ok($value) : ParsedField::fail(Reason::invalidPhone());
    }

    public static function timestamp(string $value, string $timezone): ParsedField
    {
        $zone = new DateTimeZone($timezone);
        foreach (self::TIMESTAMP_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value, $zone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return ParsedField::ok(CarbonImmutable::instance($parsed)->utc()->format('Y-m-d\TH:i:s\Z'));
            }
        }

        return ParsedField::fail(Reason::unparseableTimestamp());
    }

    public static function date(string $value, string $column): ParsedField
    {
        $candidate = preg_replace('/[ T]00:00(:00)?$/', '', $value) ?? $value;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return ParsedField::fail(Reason::unparseableDate($column));
        }

        return ParsedField::ok($parsed->format('Y-m-d'));
    }

    public static function choice(string $value, array $choices, string $column): ParsedField
    {
        if ($column === 'currency') {
            return in_array($value, $choices, true) ? ParsedField::ok($value) : ParsedField::fail(Reason::unsupportedCurrency($value));
        }

        return in_array($value, $choices, true) ? ParsedField::ok($value) : ParsedField::fail(Reason::invalidChoice($column, $value));
    }

    public static function parse(Column $column, string $value, string $timezone): ParsedField
    {
        return match ($column->type) {
            ColumnType::Money => self::money($value),
            ColumnType::Phone => self::phone($value),
            ColumnType::DateTime => self::timestamp($value, $timezone),
            ColumnType::Date => self::date($value, $column->name),
            ColumnType::Choice => self::choice($value, $column->choices, $column->name),
            ColumnType::Text => ParsedField::ok($value),
        };
    }
}
