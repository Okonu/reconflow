<?php

declare(strict_types=1);

namespace Modules\Dashboard\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum DashboardPermission: string implements PermissionEnum
{
    case View = 'dashboard.view';

    public function description(): string
    {
        return 'View the dashboard KPIs and trends';
    }

    public function group(): string
    {
        return 'Dashboard';
    }

    public function defaultRoles(): array
    {
        return DefaultRole::cases();
    }
}
