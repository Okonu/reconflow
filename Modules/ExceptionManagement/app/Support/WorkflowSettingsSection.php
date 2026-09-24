<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Support;

use App\Contracts\AuditActor;
use App\Contracts\SettingsSection;
use App\Support\Settings\VersionedSettings;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Services\WorkflowSettings;

final class WorkflowSettingsSection implements SettingsSection
{
    public function __construct(
        private readonly WorkflowSettings $workflow,
        private readonly VersionedSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function key(): string
    {
        return WorkflowSettings::SECTION;
    }

    public function label(): string
    {
        return 'Exception severity and SLAs';
    }

    public function description(): string
    {
        return 'Severity comes from the value at risk. High and Critical exceptions block sign-off. Applies to exceptions opened or reclassified after the change.';
    }

    public function order(): int
    {
        return 20;
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
        return [
            ['name' => 'medium_from', 'label' => 'Medium from (USD)', 'type' => 'money'],
            ['name' => 'high_from', 'label' => 'High from (USD)', 'type' => 'money'],
            ['name' => 'critical_from', 'label' => 'Critical from (USD)', 'type' => 'money'],
            ['name' => 'sla_low_hours', 'label' => 'Low SLA (hours)', 'type' => 'number'],
            ['name' => 'sla_medium_hours', 'label' => 'Medium SLA (hours)', 'type' => 'number'],
            ['name' => 'sla_high_hours', 'label' => 'High SLA (hours)', 'type' => 'number'],
            ['name' => 'sla_critical_hours', 'label' => 'Critical SLA (hours)', 'type' => 'number'],
        ];
    }

    public function rules(): array
    {
        $money = ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $hours = ['required', 'integer', 'min:1', 'max:720'];

        return [
            'medium_from' => $money,
            'high_from' => [...$money, 'gt:medium_from'],
            'critical_from' => [...$money, 'gt:high_from'],
            'sla_low_hours' => $hours,
            'sla_medium_hours' => $hours,
            'sla_high_hours' => $hours,
            'sla_critical_hours' => $hours,
        ];
    }

    public function values(): array
    {
        return $this->workflow->all();
    }

    public function history(): array
    {
        return $this->settings->history($this->key());
    }

    public function save(array $values, AuditActor $actor, string $comment): void
    {
        $values = array_intersect_key($values, $this->workflow->defaults());
        $stored = $this->settings->store($this->key(), $values, $actor->auditActorId(), $comment);
        $this->audit->record(ExceptionAuditAction::SettingsChanged, $actor, 'settings', null, ['section' => $this->key(), 'version' => $stored['version'], 'values' => $values, 'previous' => $stored['previous'], 'comment' => $comment]);
    }
}
