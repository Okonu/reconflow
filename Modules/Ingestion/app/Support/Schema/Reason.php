<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

final class Reason
{
    public static function missing(string $column): string
    {
        return "Missing required field: {$column}";
    }

    public static function amountNotPositive(): string
    {
        return 'Amount must be greater than 0';
    }

    public static function amountTooPrecise(): string
    {
        return 'Amount has more than 2 decimal places';
    }

    public static function invalidAmount(string $value): string
    {
        return "Invalid amount '{$value}'";
    }

    public static function invalidPhone(): string
    {
        return 'Invalid phone format (expected 2547XXXXXXXX)';
    }

    public static function unparseableTimestamp(): string
    {
        return 'Unparseable timestamp';
    }

    public static function unparseableDate(string $column): string
    {
        return "Unparseable date in {$column}";
    }

    public static function invalidChoice(string $column, string $value): string
    {
        return "Invalid {$column} '{$value}'";
    }

    public static function unsupportedCurrency(string $value): string
    {
        return "Unsupported currency '{$value}' (USD only)";
    }

    public static function businessDateMismatch(string $value, string $batchDate): string
    {
        return "Business date {$value} does not match the batch date {$batchDate}";
    }

    public static function outsideWindow(string $timestamp, string $from, string $to): string
    {
        return "Payment timestamp {$timestamp} is outside the extract window ({$from} to {$to} EAT)";
    }

    public static function exactDuplicate(int $firstRow): string
    {
        return "Duplicate row: exact copy of row {$firstRow}";
    }

    public static function conflictingKey(string $column, string $key): string
    {
        return "Conflicting records share {$column} {$key}";
    }

    public static function alreadyLoaded(string $column, string $key): string
    {
        return "{$column} {$key} already exists for this date: use Replace";
    }
}
