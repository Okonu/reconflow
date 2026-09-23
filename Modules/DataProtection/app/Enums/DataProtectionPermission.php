<?php

declare(strict_types=1);

namespace Modules\DataProtection\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum DataProtectionPermission: string implements PermissionEnum
{
    case UnmaskPersonalData = 'pii.unmask';
    case EraseSubjectData = 'privacy.erase';

    public function description(): string
    {
        return match ($this) {
            self::UnmaskPersonalData => 'Reveal masked personal data on screen (every reveal is audited)',
            self::EraseSubjectData => 'Carry out a data-subject erasure (anonymisation) request',
        };
    }

    public function group(): string
    {
        return 'Data protection';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::UnmaskPersonalData => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::EraseSubjectData => [DefaultRole::Administrator],
        };
    }
}
