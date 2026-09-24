<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Rbac\Database\Seeders\RbacDatabaseSeeder;
use Modules\Reconciliation\Database\Seeders\ReconciliationDatabaseSeeder;
use Modules\Users\Database\Seeders\UsersDatabaseSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacDatabaseSeeder::class,
            UsersDatabaseSeeder::class,
            ReconciliationDatabaseSeeder::class,
        ]);
    }
}
