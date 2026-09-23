<?php

declare(strict_types=1);

namespace Modules\Rbac\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Rbac\Actions\SeedDefaultRoles;
use Modules\Rbac\Services\PermissionCatalogue;

final class RbacDatabaseSeeder extends Seeder
{
    public function run(PermissionCatalogue $catalogue, SeedDefaultRoles $roles): void
    {
        $catalogue->sync();
        $roles->handle();
    }
}
