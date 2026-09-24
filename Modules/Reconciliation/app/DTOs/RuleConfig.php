<?php

declare(strict_types=1);

namespace Modules\Reconciliation\DTOs;

use Modules\Reconciliation\Support\Cents;

final readonly class RuleConfig
{
    public function __construct(
        public string $tolerance,
        public int $fuzzyWindowHours,
        public string $timingCutoff,
        public int $duplicateWindowMinutes,
        public int $graceHours,
        public int $timingCarryDays,
        public int $latePaymentLookbackDays,
        public string $scheduleTime,
        public int $blockedRetryMinutes,
        public int $blockedMaxAttempts,
        public string $timezone,
        public ?int $versionId = null,
        public ?int $version = null,
    ) {}

    public static function fromArray(array $values, ?int $versionId = null, ?int $version = null): self
    {
        return new self(
            tolerance: (string) $values['tolerance'],
            fuzzyWindowHours: (int) $values['fuzzy_window_hours'],
            timingCutoff: (string) $values['timing_cutoff'],
            duplicateWindowMinutes: (int) $values['duplicate_window_minutes'],
            graceHours: (int) ($values['grace_hours'] ?? config('reconflow.payments_window_grace_hours')),
            timingCarryDays: (int) $values['timing_carry_days'],
            latePaymentLookbackDays: (int) $values['late_payment_lookback_days'],
            scheduleTime: (string) $values['schedule_time'],
            blockedRetryMinutes: (int) $values['blocked_retry_minutes'],
            blockedMaxAttempts: (int) $values['blocked_max_attempts'],
            timezone: (string) ($values['timezone'] ?? config('reconflow.display_timezone')),
            versionId: $versionId,
            version: $version,
        );
    }

    public function toArray(): array
    {
        return [
            'tolerance' => $this->tolerance,
            'fuzzy_window_hours' => $this->fuzzyWindowHours,
            'timing_cutoff' => $this->timingCutoff,
            'duplicate_window_minutes' => $this->duplicateWindowMinutes,
            'grace_hours' => $this->graceHours,
            'timing_carry_days' => $this->timingCarryDays,
            'late_payment_lookback_days' => $this->latePaymentLookbackDays,
            'schedule_time' => $this->scheduleTime,
            'blocked_retry_minutes' => $this->blockedRetryMinutes,
            'blocked_max_attempts' => $this->blockedMaxAttempts,
            'timezone' => $this->timezone,
            'version' => $this->version,
        ];
    }

    public function toleranceCents(): int
    {
        return Cents::fromDecimal($this->tolerance);
    }

    public function fuzzyWindowSeconds(): int
    {
        return $this->fuzzyWindowHours * 3600;
    }

    public function duplicateWindowSeconds(): int
    {
        return $this->duplicateWindowMinutes * 60;
    }
}
