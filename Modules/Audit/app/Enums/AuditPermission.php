<?php

declare(strict_types=1);

namespace Modules\Audit\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum AuditPermission: string implements PermissionEnum
{
    case View = 'audit.view';
    case Verify = 'audit.verify';
    case Export = 'audit.export';

    public function description(): string
    {
        return match ($this) {
            self::View => 'View the audit log',
            self::Verify => 'Run the audit-chain integrity check',
            self::Export => 'Export the audit log',
        };
    }

    public function group(): string
    {
        return 'Audit';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View, self::Verify => DefaultRole::cases(),
            self::Export => [DefaultRole::FinanceManager, DefaultRole::Auditor, DefaultRole::Administrator],
        };
    }
}
