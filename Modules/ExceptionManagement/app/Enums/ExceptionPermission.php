<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum ExceptionPermission: string implements PermissionEnum
{
    case View = 'exceptions.view';
    case Work = 'exceptions.work';
    case Assign = 'exceptions.assign';
    case SignOff = 'runs.signoff';
    case Reopen = 'runs.reopen';

    public function description(): string
    {
        return match ($this) {
            self::View => 'View the exception queue and exception details',
            self::Work => 'Work exceptions: review, comment, resolve without action',
            self::Assign => 'Assign and reassign exceptions',
            self::SignOff => 'Sign off a business date (locks its results)',
            self::Reopen => 'Reopen a signed-off business date (reason required)',
        };
    }

    public function group(): string
    {
        return 'Exceptions';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View => DefaultRole::cases(),
            self::Work, self::Assign => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::SignOff, self::Reopen => [DefaultRole::FinanceManager],
        };
    }
}
