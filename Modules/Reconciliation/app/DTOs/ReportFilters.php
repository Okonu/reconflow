<?php

declare(strict_types=1);

namespace Modules\Reconciliation\DTOs;

final readonly class ReportFilters
{
    public function __construct(
        public string $date,
        public string $section = 'current',
        public ?string $rollUp = null,
        public ?string $status = null,
        public ?string $search = null,
    ) {}

    public function toArray(): array
    {
        return ['date' => $this->date, 'section' => $this->section, 'roll_up' => $this->rollUp, 'status' => $this->status, 'search' => $this->search];
    }
}
