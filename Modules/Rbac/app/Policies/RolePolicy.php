<?php

declare(strict_types=1);

namespace Modules\Rbac\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Rbac\Enums\RbacPermission;
use Modules\Rbac\Models\Role;

final class RolePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, RbacPermission::ViewRoles);
    }

    public function create(User $user): Response
    {
        return $this->permit($user, RbacPermission::ManageRoles);
    }

    public function update(User $user, Role $role): Response
    {
        return $this->permit($user, RbacPermission::ManageRoles);
    }

    public function delete(User $user, Role $role): Response
    {
        if ($role->is_system) {
            return Response::deny('System roles cannot be deleted');
        }

        return $this->permit($user, RbacPermission::ManageRoles);
    }

    public function assign(User $user): Response
    {
        return $this->permit($user, RbacPermission::ManageRoles);
    }
}
