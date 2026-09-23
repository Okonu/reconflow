<?php

declare(strict_types=1);

namespace Modules\Users\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum UserPermission: string implements PermissionEnum
{
    case View = 'users.view';
    case Manage = 'users.manage';

    public function description(): string
    {
        return match ($this) {
            self::View => 'View user accounts',
            self::Manage => 'Create, update and deactivate user accounts',
        };
    }

    public function group(): string
    {
        return 'Users';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View => [DefaultRole::Auditor, DefaultRole::Administrator],
            self::Manage => [DefaultRole::Administrator],
        };
    }
}
