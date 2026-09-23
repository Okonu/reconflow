<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Demo;

use App\Contracts\ResetsDemoData;
use Illuminate\Support\Facades\DB;

final class IngestionTables implements ResetsDemoData
{
    public const TABLES = ['quarantined_rows', 'sales_records', 'payment_records', 'posting_records', 'upload_staging', 'source_batches', 'mock_source_rows'];

    public function resetOrder(): int
    {
        return 100;
    }

    public function truncateOperationalData(): array
    {
        DB::statement('truncate table '.implode(', ', self::TABLES).' restart identity cascade');

        return self::TABLES;
    }
}
