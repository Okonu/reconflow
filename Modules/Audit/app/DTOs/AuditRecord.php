<?php

declare(strict_types=1);

namespace Modules\Audit\DTOs;

use Carbon\CarbonImmutable;
use Modules\Audit\Support\AuditHasher;

final readonly class AuditRecord
{
    public function __construct(
        public int $id,
        public CarbonImmutable $occurredAt,
        public ?int $actorId,
        public string $actorLabel,
        public string $action,
        public ?string $entityType,
        public ?string $entityId,
        public ?string $requestId,
        public array $payload,
        public string $prevHash,
        public string $hash,
    ) {}

    public static function fromRow(object|array $row): self
    {
        $row = (array) $row;
        $payload = $row['payload'] ?? [];

        return new self(
            id: (int) $row['id'],
            occurredAt: CarbonImmutable::parse($row['occurred_at']),
            actorId: isset($row['actor_id']) ? (int) $row['actor_id'] : null,
            actorLabel: (string) $row['actor_label'],
            action: (string) $row['action'],
            entityType: $row['entity_type'] ?? null,
            entityId: $row['entity_id'] ?? null,
            requestId: $row['request_id'] ?? null,
            payload: is_string($payload) ? (array) json_decode($payload, true, flags: JSON_THROW_ON_ERROR) : (array) $payload,
            prevHash: (string) $row['prev_hash'],
            hash: (string) $row['hash'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurred_at' => AuditHasher::timestamp($this->occurredAt),
            'actor_id' => $this->actorId,
            'actor_label' => $this->actorLabel,
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'request_id' => $this->requestId,
            'payload' => $this->payload,
            'prev_hash' => $this->prevHash,
            'hash' => $this->hash,
        ];
    }
}
