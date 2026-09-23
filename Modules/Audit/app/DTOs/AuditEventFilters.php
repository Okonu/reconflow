<?php

declare(strict_types=1);

namespace Modules\Audit\DTOs;

use Carbon\CarbonImmutable;

final readonly class AuditEventFilters
{
    public function __construct(
        public ?string $action = null,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?string $actor = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'actor' => $this->actor,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
        ];
    }
}
