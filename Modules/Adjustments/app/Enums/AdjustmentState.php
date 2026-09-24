<?php

declare(strict_types=1);

namespace Modules\Adjustments\Enums;

enum AdjustmentState: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case PostingFailed = 'posting_failed';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
