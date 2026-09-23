<?php

declare(strict_types=1);

namespace Modules\Audit\Enums;

enum AuditAction: string
{
    case ChainVerified = 'audit.verified';
    case ArchiveRun = 'retention.audit_archive';
}
