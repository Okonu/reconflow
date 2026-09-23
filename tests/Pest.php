<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Rbac\Models\Role;
use Modules\Users\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../Modules/*/tests/Feature', 'Unit', '../Modules/*/tests/Unit');

const DEMO_PASSWORD = 'Demo-Password-2026';

foreach (glob(__DIR__.'/../Modules/*/tests/Helpers.php') ?: [] as $helpers) {
    require_once $helpers;
}

function demoUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

function userWithPermissions(array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'custom_'.Str::lower(Str::random(8)),
        'guard_name' => 'web',
        'label' => 'Custom',
        'description' => 'Test role',
    ]);
    $role->syncPermissions($permissions);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function samplePath(string $relative): string
{
    return base_path('samples/'.$relative);
}

function uploadedCopy(string $path, ?string $name = null): UploadedFile
{
    $copy = tempnam(sys_get_temp_dir(), 'up');
    copy($path, $copy);

    return new UploadedFile($copy, $name ?? basename($path), null, null, true);
}
