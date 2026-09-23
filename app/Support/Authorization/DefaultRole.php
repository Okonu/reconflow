<?php

declare(strict_types=1);

namespace App\Support\Authorization;

enum DefaultRole: string
{
    case ReconAnalyst = 'recon_analyst';
    case FinanceManager = 'finance_manager';
    case Auditor = 'auditor';
    case Administrator = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::ReconAnalyst => 'Recon Analyst',
            self::FinanceManager => 'Finance Manager',
            self::Auditor => 'Auditor',
            self::Administrator => 'Administrator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReconAnalyst => 'Preparer: runs reconciliations, uploads data, works exceptions, proposes adjustments',
            self::FinanceManager => 'Approver: analyst capabilities plus approvals and run sign-off',
            self::Auditor => 'Read-only access to reports, the audit log and exports',
            self::Administrator => 'Users, roles, settings and AI controls',
        };
    }

    public function isSystem(): bool
    {
        return $this === self::Administrator;
    }
}
