<?php

declare(strict_types=1);

namespace Modules\Rbac\Actions;

use App\Contracts\AuditActor;
use App\Contracts\RoleAssignable;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Rbac\Enums\RbacAuditAction;
use Modules\Rbac\Models\Role;
use Modules\Rbac\Services\AdministrationGuard;

final class AssignUserRoles
{
    public function __construct(
        private readonly AdministrationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(AuditActor|string|null $actor, RoleAssignable $user, array $roleIds): void
    {
        $roles = Role::query()->whereKey($roleIds)->get();
        if ($roles->count() !== count(array_unique($roleIds))) {
            throw DomainException::invalid('One or more roles do not exist');
        }

        DB::transaction(function () use ($actor, $user, $roles): void {
            $before = $user->roles()->pluck('name')->sort()->values()->all();
            $this->guard->preserving(function () use ($user, $roles): void {
                $user->syncRoles($roles);
            });
            $after = $roles->pluck('name')->sort()->values()->all();

            if ($before !== $after) {
                $this->audit->record(RbacAuditAction::UserRolesChanged, $actor, 'user', $user->getKey(), [
                    'before' => $before,
                    'after' => $after,
                ]);
            }
        });
    }
}
