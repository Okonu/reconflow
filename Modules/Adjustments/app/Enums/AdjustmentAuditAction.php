<?php

declare(strict_types=1);

namespace Modules\Adjustments\Enums;

enum AdjustmentAuditAction: string
{
    case Proposed = 'adjustment.proposed';
    case Approved = 'adjustment.approved';
    case Rejected = 'adjustment.rejected';
    case Posted = 'adjustment.posted';
    case PostingFailed = 'adjustment.posting_failed';
    case SettingsChanged = 'settings.approvals_changed';
    case ErpFailureToggled = 'erp.failure_simulation_toggled';
}
