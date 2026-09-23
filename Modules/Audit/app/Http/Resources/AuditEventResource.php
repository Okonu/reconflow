<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Models\AuditEvent;

final class AuditEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->resource;
        assert($event instanceof AuditEvent);

        return [
            'id' => $event->id,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'actor' => $event->actor_label,
            'action' => $event->action,
            'entity_type' => $event->entity_type,
            'entity_id' => $event->entity_id,
            'request_id' => $event->request_id,
            'payload' => $event->payload,
            'hash' => $event->hash,
            'prev_hash' => $event->prev_hash,
        ];
    }
}
