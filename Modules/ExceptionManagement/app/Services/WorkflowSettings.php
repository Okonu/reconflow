<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use App\Support\Settings\VersionedSettings;
use Modules\ExceptionManagement\Enums\Severity;

final class WorkflowSettings
{
    public const SECTION = 'workflow';

    public function __construct(private readonly VersionedSettings $settings) {}

    public function defaults(): array
    {
        return [
            'medium_from' => (string) config('exceptionmanagement.severity.medium_from'),
            'high_from' => (string) config('exceptionmanagement.severity.high_from'),
            'critical_from' => (string) config('exceptionmanagement.severity.critical_from'),
            'sla_low_hours' => (int) config('exceptionmanagement.sla_hours.low'),
            'sla_medium_hours' => (int) config('exceptionmanagement.sla_hours.medium'),
            'sla_high_hours' => (int) config('exceptionmanagement.sla_hours.high'),
            'sla_critical_hours' => (int) config('exceptionmanagement.sla_hours.critical'),
        ];
    }

    public function all(): array
    {
        return $this->settings->current(self::SECTION, $this->defaults());
    }

    public function bands(): array
    {
        $all = $this->all();

        return ['medium_from' => (string) $all['medium_from'], 'high_from' => (string) $all['high_from'], 'critical_from' => (string) $all['critical_from']];
    }

    public function slaHours(Severity $severity): int
    {
        return (int) $this->all()["sla_{$severity->value}_hours"];
    }
}
