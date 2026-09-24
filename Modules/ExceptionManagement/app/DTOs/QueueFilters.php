<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\DTOs;

final readonly class QueueFilters
{
    public function __construct(
        public ?string $state = 'open',
        public ?string $category = null,
        public ?string $severity = null,
        public ?string $owner = null,
        public bool $overdue = false,
        public ?string $businessDate = null,
        public ?string $search = null,
    ) {}

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'category' => $this->category,
            'severity' => $this->severity,
            'owner' => $this->owner,
            'overdue' => $this->overdue,
            'business_date' => $this->businessDate,
            'search' => $this->search,
        ];
    }
}
