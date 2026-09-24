<?php

declare(strict_types=1);

namespace Modules\Adjustments\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum AdjustmentPermission: string implements PermissionEnum
{
    case View = 'adjustments.view';
    case Propose = 'adjustments.propose';
    case Approve = 'adjustments.approve';
    case ApproveHighValue = 'adjustments.approve_high_value';
    case ManageErp = 'erp.manage';

    public function description(): string
    {
        return match ($this) {
            self::View => 'View adjustments and the approvals inbox',
            self::Propose => 'Propose a correcting adjustment (maker)',
            self::Approve => 'Approve or reject adjustments proposed by someone else (checker)',
            self::ApproveHighValue => 'Approve adjustments above the approval threshold',
            self::ManageErp => 'Turn simulated ERP failures on or off',
        };
    }

    public function group(): string
    {
        return 'Adjustments';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View => DefaultRole::cases(),
            self::Propose => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::Approve, self::ApproveHighValue => [DefaultRole::FinanceManager],
            self::ManageErp => [DefaultRole::Administrator],
        };
    }
}
