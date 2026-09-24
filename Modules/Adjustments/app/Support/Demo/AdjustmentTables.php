<?php

declare(strict_types=1);

namespace Modules\Adjustments\Support\Demo;

use App\Contracts\ResetsDemoData;
use Illuminate\Support\Facades\DB;

final class AdjustmentTables implements ResetsDemoData
{
    public const TABLES = ['erp_postings_out', 'adjustments'];

    public function resetOrder(): int
    {
        return 10;
    }

    public function truncateOperationalData(): array
    {
        DB::statement('truncate table '.implode(', ', self::TABLES).' restart identity cascade');

        return self::TABLES;
    }
}
