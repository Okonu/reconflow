<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Reconciliation\Services\RuleConfigService;

final class ReconciliationDatabaseSeeder extends Seeder
{
    public function run(RuleConfigService $configs): void
    {
        $configs->ensureSeeded();
    }
}
