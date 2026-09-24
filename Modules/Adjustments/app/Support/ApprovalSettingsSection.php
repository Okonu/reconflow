<?php

declare(strict_types=1);

namespace Modules\Adjustments\Support;

use App\Contracts\AuditActor;
use App\Contracts\SettingsSection;
use App\Support\Settings\VersionedSettings;
use Modules\Adjustments\Enums\AdjustmentAuditAction;
use Modules\Adjustments\Services\ApprovalSettings;
use Modules\Audit\Services\AuditLogger;

final class ApprovalSettingsSection implements SettingsSection
{
    public function __construct(
        private readonly ApprovalSettings $approvals,
        private readonly VersionedSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function key(): string
    {
        return ApprovalSettings::SECTION;
    }

    public function label(): string
    {
        return 'Adjustment approvals';
    }

    public function description(): string
    {
        return 'Adjustments above the threshold need a Finance Manager (adjustments.approve_high_value). The proposer can never approve their own adjustment.';
    }

    public function order(): int
    {
        return 30;
    }

    public function viewPermission(): string
    {
        return 'config.view';
    }

    public function managePermission(): string
    {
        return 'config.manage';
    }

    public function fields(): array
    {
        return [['name' => 'approval_threshold', 'label' => 'High-value approval threshold (USD)', 'type' => 'money']];
    }

    public function rules(): array
    {
        return ['approval_threshold' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/']];
    }

    public function values(): array
    {
        return $this->approvals->all();
    }

    public function history(): array
    {
        return $this->settings->history($this->key());
    }

    public function save(array $values, AuditActor $actor, string $comment): void
    {
        $values = array_intersect_key($values, $this->approvals->defaults());
        $stored = $this->settings->store($this->key(), $values, $actor->auditActorId(), $comment);
        $this->audit->record(AdjustmentAuditAction::SettingsChanged, $actor, 'settings', null, ['section' => $this->key(), 'version' => $stored['version'], 'values' => $values, 'previous' => $stored['previous'], 'comment' => $comment]);
    }
}
