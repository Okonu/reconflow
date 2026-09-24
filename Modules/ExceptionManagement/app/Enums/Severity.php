<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function blocksSignOff(): bool
    {
        return $this->rank() >= self::High->rank();
    }

    public function slaHours(): int
    {
        return (int) config("exceptionmanagement.sla_hours.{$this->value}");
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
