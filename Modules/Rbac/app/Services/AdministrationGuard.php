<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use App\Exceptions\DomainException;
use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionRegistry;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Modules\Rbac\Models\Role;

final class AdministrationGuard
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    public function preserving(Closure $change): mixed
    {
        $before = $this->activeAdministrators();
        $result = $change();

        $this->assertAdministratorRoleIntact();
        if ($before > 0 && $this->activeAdministrators() === 0) {
            throw DomainException::conflict('At least one active user must keep user and role management permissions');
        }

        return $result;
    }

    private function assertAdministratorRoleIntact(): void
    {
        $admin = Role::query()->where('name', DefaultRole::Administrator->value)->first();
        if ($admin === null) {
            return;
        }
        $missing = array_values(array_diff($this->registry->protectedCodes(), $admin->permissions()->pluck('name')->all()));
        if ($missing !== []) {
            throw DomainException::conflict('The Administrator role must keep: '.implode(', ', $missing));
        }
    }

    private function activeAdministrators(): int
    {
        $userModel = (string) config('auth.providers.users.model');
        $query = $userModel::query()->where('is_active', true);
        foreach ($this->registry->protectedCodes() as $permission) {
            $query->whereHas('roles.permissions', fn (Builder $q) => $q->where('name', $permission));
        }

        return $query->count();
    }
}
