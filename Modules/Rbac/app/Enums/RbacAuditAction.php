<?php

declare(strict_types=1);

namespace Modules\Rbac\Enums;

enum RbacAuditAction: string
{
    case PermissionsSynced = 'permissions.synced';
    case RoleSeeded = 'role.seeded';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case UserRolesChanged = 'user.roles_changed';
}
