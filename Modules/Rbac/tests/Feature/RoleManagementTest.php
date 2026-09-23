<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;
use Modules\Rbac\Models\Role;

it('seeds each default role with the permissions modules declare for it', function (string $email, array $has, array $lacks): void {
    $user = demoUser($email);
    foreach ($has as $permission) {
        expect($user->can($permission))->toBeTrue("{$email} should have {$permission}");
    }
    foreach ($lacks as $permission) {
        expect($user->can($permission))->toBeFalse("{$email} should not have {$permission}");
    }
})->with([
    ['analyst@demo', ['audit.view', 'audit.verify', 'pii.unmask'], ['roles.manage', 'users.manage', 'audit.export']],
    ['manager@demo', ['audit.view', 'audit.export', 'pii.unmask'], ['roles.manage', 'users.manage']],
    ['auditor@demo', ['audit.view', 'audit.export', 'users.view', 'roles.view'], ['pii.unmask', 'roles.manage']],
    ['admin@demo', ['users.manage', 'roles.manage', 'privacy.erase'], ['pii.unmask']],
]);

it('lets an administrator define a role whose access applies immediately', function (): void {
    $admin = demoUser('admin@demo');
    $manager = demoUser('manager@demo');

    $this->actingAs($admin)->post(route('rbac.roles.store'), [
        'code' => 'audit_reader', 'label' => 'Audit reader', 'permissions' => ['audit.view'],
    ])->assertSessionHas('success');
    $role = Role::query()->where('name', 'audit_reader')->firstOrFail();
    $this->actingAs($admin)->put(route('rbac.users.roles', $manager->id), ['role_ids' => [$role->id]])->assertSessionHas('success');

    $this->actingAs($manager->fresh())->get(route('audit.index'))->assertOk();
    $this->actingAs($manager->fresh())->postJson(route('audit.verify'))->assertForbidden();

    $this->actingAs($admin)->patch(route('rbac.roles.update', $role), ['permissions' => []]);
    $this->actingAs($manager->fresh())->get(route('audit.index'))->assertForbidden();

    expect(AuditEvent::query()->pluck('action')->all())->toContain('role.created', 'role.updated', 'user.roles_changed');
});

it('audits exactly which permissions were granted and revoked', function (): void {
    $role = Role::query()->where('name', 'auditor')->firstOrFail();
    $permissions = collect($role->permissionCodes())->reject(fn ($p) => $p === 'audit.export')->push('pii.unmask')->values()->all();

    $this->actingAs(demoUser('admin@demo'))->patch(route('rbac.roles.update', $role), ['permissions' => $permissions]);

    $payload = AuditEvent::query()->where('action', 'role.updated')->latest('id')->firstOrFail()->payload;
    expect($payload['granted'])->toBe(['pii.unmask'])->and($payload['revoked'])->toBe(['audit.export']);
});

it('protects the Administrator role from deletion and from losing protected permissions', function (): void {
    $admin = demoUser('admin@demo');
    $role = Role::query()->where('name', 'admin')->firstOrFail();

    $this->actingAs($admin)->delete(route('rbac.roles.destroy', $role))->assertForbidden();

    $stripped = collect($role->permissionCodes())->reject(fn ($p) => $p === 'roles.manage')->values()->all();
    $this->actingAs($admin)->patch(route('rbac.roles.update', $role), ['permissions' => $stripped])->assertSessionHas('error');
    expect($role->fresh()->permissionCodes())->toContain('roles.manage');

    $reduced = collect($role->permissionCodes())->reject(fn ($p) => $p === 'privacy.erase')->values()->all();
    $this->actingAs($admin)->patch(route('rbac.roles.update', $role), ['permissions' => $reduced])->assertSessionHas('success');
});

it('rejects unknown permissions and deleting a role that is in use', function (): void {
    $admin = demoUser('admin@demo');

    $this->actingAs($admin)->post(route('rbac.roles.store'), [
        'code' => 'bad', 'label' => 'Bad', 'permissions' => ['launch.missiles'],
    ])->assertSessionHas('error');

    $this->actingAs($admin)->delete(route('rbac.roles.destroy', Role::query()->where('name', 'recon_analyst')->firstOrFail()))
        ->assertSessionHas('error');
});

it('prevents removing the last administrator through role reassignment', function (): void {
    $admin = demoUser('admin@demo');
    $auditorRole = Role::query()->where('name', 'auditor')->firstOrFail();

    $this->actingAs($admin)->put(route('rbac.users.roles', $admin->id), ['role_ids' => [$auditorRole->id]])
        ->assertSessionHas('error');
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});
