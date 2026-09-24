<?php

declare(strict_types=1);

namespace Modules\Notifications\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum NotificationPermission: string implements PermissionEnum
{
    case RunAlerts = 'notifications.run_alerts';
    case CriticalAlerts = 'notifications.critical_alerts';
    case DailySummary = 'notifications.daily_summary';

    public function description(): string
    {
        return match ($this) {
            self::RunAlerts => 'Receive alerts when a reconciliation run fails or is blocked by missing data',
            self::CriticalAlerts => 'Receive alerts when critical exceptions are opened',
            self::DailySummary => 'Receive the daily reconciliation summary',
        };
    }

    public function group(): string
    {
        return 'Notifications';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::RunAlerts => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager, DefaultRole::Administrator],
            self::CriticalAlerts => [DefaultRole::FinanceManager],
            self::DailySummary => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
        };
    }
}
