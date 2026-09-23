<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Carbon\CarbonImmutable;

final readonly class ValidationContext
{
    public function __construct(
        public CarbonImmutable $businessDate,
        public string $timezone,
        public int $graceHours,
    ) {}

    public static function forDate(CarbonImmutable|string $date): self
    {
        $timezone = (string) config('reconflow.display_timezone');

        return new self(
            CarbonImmutable::parse($date instanceof CarbonImmutable ? $date->toDateString() : $date, $timezone)->startOfDay(),
            $timezone,
            (int) config('reconflow.payments_window_grace_hours'),
        );
    }

    public function windowStart(): CarbonImmutable
    {
        return $this->businessDate;
    }

    public function windowEnd(): CarbonImmutable
    {
        return $this->businessDate->addDay()->addHours($this->graceHours);
    }

    public function dateString(): string
    {
        return $this->businessDate->toDateString();
    }
}
