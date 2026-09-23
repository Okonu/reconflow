<?php

declare(strict_types=1);

namespace Modules\Rbac\Actions;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\DTOs\RoleChanges;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Role;
use Modules\Rbac\Services\AdministrationGuard;
use Modules\Rbac\Services\PermissionCatalogue;

final class UpdateRole
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue,
        private readonly AdministrationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(AuditActor $actor, Role $role, RoleChanges $changes): Role
    {
        if ($changes->permissions !== null) {
            $this->catalogue->assertKnown($changes->permissions);
        }

        return DB::transaction(function () use ($actor, $role, $changes): Role {
            $before = $role->permissionCodes();
            $role->fill(array_filter([
                'label' => $changes->label,
                'description' => $changes->description,
            ], fn ($v) => $v !== null))->save();

            $this->guard->preserving(function () use ($role, $changes): void {
                if ($changes->permissions !== null) {
                    $role->syncPermissions($changes->permissions);
                }
                $role->unsetRelation('permissions');
            });
            $after = $role->permissionCodes();

            $this->audit->record(RbacAuditAction::RoleUpdated, $actor, 'role', $role->id, [
                'code' => $role->name,
                'label' => $role->label,
                'granted' => array_values(array_diff($after, $before)),
                'revoked' => array_values(array_diff($before, $after)),
            ]);

            return $role;
        });
    }
}
