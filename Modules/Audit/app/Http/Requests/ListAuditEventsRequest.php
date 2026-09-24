<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\DTOs\AuditEventFilters;
use Modules\Audit\Models\AuditEvent;

final class ListAuditEventsRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect($this->routeIs('audit.export') ? 'export' : 'viewAny', AuditEvent::class);
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:64'],
            'entity_id' => ['nullable', 'string', 'max:128'],
            'actor' => ['nullable', 'string', 'max:254'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:10,200'],
        ];
    }

    public function filters(): AuditEventFilters
    {
        return new AuditEventFilters(
            action: $this->validated('action'),
            entityType: $this->validated('entity_type'),
            entityId: $this->validated('entity_id'),
            actor: $this->validated('actor'),
            from: $this->date('from')?->toImmutable(),
            to: $this->date('to')?->toImmutable()->addDay(),
        );
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }
}
