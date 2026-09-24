<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Support\Demo;

use App\Contracts\ResetsDemoData;
use Illuminate\Support\Facades\DB;

final class ExceptionTables implements ResetsDemoData
{
    public const TABLES = ['exception_events', 'run_signoffs', 'exceptions'];

    public function resetOrder(): int
    {
        return 20;
    }

    public function truncateOperationalData(): array
    {
        DB::statement('truncate table '.implode(', ', self::TABLES).' restart identity cascade');

        return self::TABLES;
    }
}
