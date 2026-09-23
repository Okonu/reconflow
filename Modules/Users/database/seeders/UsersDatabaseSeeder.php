<?php

declare(strict_types=1);

namespace Modules\Users\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Users\Actions\SeedDemoUsers;

final class UsersDatabaseSeeder extends Seeder
{
    public function run(SeedDemoUsers $demoUsers): void
    {
        $password = config('users.demo.password');
        if (config('users.demo.seed') && is_string($password) && $password !== '') {
            $demoUsers->handle($password);
        }
    }
}
