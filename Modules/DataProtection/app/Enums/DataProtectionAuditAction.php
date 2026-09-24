<?php

declare(strict_types=1);

namespace Modules\DataProtection\Enums;

enum DataProtectionAuditAction: string
{
    case Unmasked = 'pii.unmasked';
    case RetentionAnonymised = 'retention.transactions_anonymised';
    case SubjectErased = 'privacy.subject_erased';
}
