<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Support\Demo;

use App\Contracts\ResetsDemoData;
use Illuminate\Support\Facades\DB;

final class ReconciliationTables implements ResetsDemoData
{
    public const TABLES = ['recon_manual_matches', 'recon_item_states', 'recon_results', 'recon_runs'];

    public function resetOrder(): int
    {
        return 50;
    }

    public function truncateOperationalData(): array
    {
        DB::statement('truncate table '.implode(', ', self::TABLES).' restart identity cascade');

        return self::TABLES;
    }
}
