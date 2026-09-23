<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Rbac\Models\Role;

it('lists users with their roles for users.view holders', function (): void {
    $this->actingAs(demoUser('auditor@demo'))
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 4)
            ->where('can.create', false));
});

it('creates a user with roles, lower-cases the email and allows login', function (): void {
    $auditor = Role::query()->where('name', 'auditor')->firstOrFail();

    $this->actingAs(demoUser('admin@demo'))->post(route('users.store'), [
        'name' => 'New Auditor',
        'email' => 'New.Auditor@Test',
        'password' => 'a-long-password-1',
        'role_ids' => [$auditor->id],
    ])->assertSessionHas('success');

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'new.auditor@test', 'password' => 'a-long-password-1']);
    $this->assertAuthenticated();
    expect(auth()->user()->hasRole('auditor'))->toBeTrue();
});

it('rejects duplicate emails', function (): void {
    $role = Role::query()->where('name', 'auditor')->firstOrFail();

    $this->actingAs(demoUser('admin@demo'))->post(route('users.store'), [
        'name' => 'Dup', 'email' => 'ANALYST@demo', 'password' => 'a-long-password-1', 'role_ids' => [$role->id],
    ])->assertSessionHas('error', 'A user with this email already exists');
});

it('will not deactivate the last active administrator', function (): void {
    $admin = demoUser('admin@demo');

    $this->actingAs($admin)->patch(route('users.update', $admin), ['is_active' => false])
        ->assertSessionHas('error');
    expect($admin->fresh()->is_active)->toBeTrue();
});
