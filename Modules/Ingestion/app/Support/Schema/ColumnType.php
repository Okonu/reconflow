<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

enum ColumnType: string
{
    case Text = 'text';
    case Date = 'date';
    case DateTime = 'datetime';
    case Money = 'money';
    case Phone = 'phone';
    case Choice = 'choice';

    public function formatLabel(): string
    {
        return match ($this) {
            self::Text, self::Phone => 'Text',
            self::Date => 'Date (YYYY-MM-DD)',
            self::DateTime => 'Date-time (YYYY-MM-DD HH:MM:SS, EAT)',
            self::Money => 'Number, 2 decimals',
            self::Choice => 'List',
        };
    }

    public function excelNumberFormat(): string
    {
        return match ($this) {
            self::Text, self::Phone => '@',
            self::Date => 'yyyy-mm-dd',
            self::DateTime => 'yyyy-mm-dd hh:mm:ss',
            self::Money => '#,##0.00',
            self::Choice => 'General',
        };
    }
}
