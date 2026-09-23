<?php

declare(strict_types=1);

namespace Modules\Rbac\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum RbacPermission: string implements PermissionEnum
{
    case ViewRoles = 'roles.view';
    case ManageRoles = 'roles.manage';

    public function description(): string
    {
        return match ($this) {
            self::ViewRoles => 'View roles and their permissions',
            self::ManageRoles => 'Create roles, change their permissions and assign them to users',
        };
    }

    public function group(): string
    {
        return 'Access control';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewRoles => [DefaultRole::Auditor, DefaultRole::Administrator],
            self::ManageRoles => [DefaultRole::Administrator],
        };
    }
}
