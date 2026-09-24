<?php

declare(strict_types=1);

namespace Modules\AI\Support\Demo;

use App\Contracts\ResetsDemoData;
use Illuminate\Support\Facades\DB;

final class AiTables implements ResetsDemoData
{
    public const TABLES = ['ai_suggestions'];

    public function resetOrder(): int
    {
        return 5;
    }

    public function truncateOperationalData(): array
    {
        DB::statement('truncate table '.implode(', ', self::TABLES).' restart identity cascade');

        return self::TABLES;
    }
}
