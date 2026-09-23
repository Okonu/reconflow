<?php

declare(strict_types=1);

namespace Modules\Rbac\Actions;

use App\Contracts\AuditActor;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\DTOs\RoleData;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Role;
use Modules\Rbac\Services\PermissionCatalogue;

final class CreateRole
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(AuditActor $actor, RoleData $data): Role
    {
        $this->catalogue->assertKnown($data->permissions);

        return DB::transaction(function () use ($actor, $data): Role {
            if (Role::query()->where('name', $data->code)->exists()) {
                throw DomainException::conflict("Role '{$data->code}' already exists");
            }
            $role = Role::query()->create([
                'name' => $data->code,
                'guard_name' => PermissionCatalogue::GUARD,
                'label' => $data->label,
                'description' => $data->description,
                'is_system' => false,
            ]);
            $role->syncPermissions($data->permissions);

            $this->audit->record(RbacAuditAction::RoleCreated, $actor, 'role', $role->id, [
                'code' => $role->name,
                'permissions' => $role->permissionCodes(),
            ]);

            return $role;
        });
    }
}
