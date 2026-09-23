<?php

declare(strict_types=1);

namespace Modules\Rbac\Actions;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Role;
use Modules\Rbac\Services\PermissionCatalogue;

final class SeedDefaultRoles
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            foreach (DefaultRole::cases() as $template) {
                if (Role::query()->where('name', $template->value)->exists()) {
                    continue;
                }
                $role = Role::query()->create([
                    'name' => $template->value,
                    'guard_name' => PermissionCatalogue::GUARD,
                    'label' => $template->label(),
                    'description' => $template->description(),
                    'is_system' => $template->isSystem(),
                ]);
                $permissions = $this->registry->defaultsFor($template);
                $role->syncPermissions($permissions);

                $this->audit->record(RbacAuditAction::RoleSeeded, entityType: 'role', entityId: $role->id, payload: [
                    'code' => $role->name,
                    'permissions' => $permissions,
                ]);
            }
        });
    }
}
