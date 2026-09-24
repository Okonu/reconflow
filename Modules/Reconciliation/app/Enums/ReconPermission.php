<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum ReconPermission: string implements PermissionEnum
{
    case ViewRuns = 'runs.view';
    case TriggerRuns = 'runs.trigger';
    case ViewResults = 'results.view';
    case ExportResults = 'results.export';
    case ExportResultsUnmasked = 'results.export_unmasked';
    case ConfirmMatches = 'matches.confirm';
    case ViewConfig = 'config.view';
    case ManageConfig = 'config.manage';

    public function description(): string
    {
        return match ($this) {
            self::ViewRuns => 'View reconciliation runs and their data-quality inputs',
            self::TriggerRuns => 'Run a reconciliation (Run now)',
            self::ViewResults => 'View the reconciliation report',
            self::ExportResults => 'Export the reconciliation report with personal data masked',
            self::ExportResultsUnmasked => 'Export the reconciliation report unmasked (reason required, audited)',
            self::ConfirmMatches => 'Confirm a suggested late-payment match (audited manual match)',
            self::ViewConfig => 'View reconciliation rule settings',
            self::ManageConfig => 'Change reconciliation rule settings (versioned, audited)',
        };
    }

    public function group(): string
    {
        return 'Reconciliation';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewRuns, self::ViewResults, self::ViewConfig => DefaultRole::cases(),
            self::TriggerRuns, self::ConfirmMatches => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::ExportResults => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager, DefaultRole::Auditor],
            self::ExportResultsUnmasked => [DefaultRole::FinanceManager],
            self::ManageConfig => [DefaultRole::Administrator],
        };
    }
}
