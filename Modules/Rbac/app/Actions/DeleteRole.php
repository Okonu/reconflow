<?php

declare(strict_types=1);

namespace Modules\Rbac\Actions;

use App\Contracts\AuditActor;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Role;

final class DeleteRole
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(AuditActor $actor, Role $role): void
    {
        if ($role->is_system) {
            throw DomainException::conflict('System roles cannot be deleted');
        }
        if ($role->users()->exists()) {
            throw DomainException::conflict('This role is assigned to users; reassign them first');
        }

        DB::transaction(function () use ($actor, $role): void {
            $id = $role->id;
            $code = $role->name;
            $role->delete();
            $this->audit->record(RbacAuditAction::RoleDeleted, $actor, 'role', $id, ['code' => $code]);
        });
    }
}
