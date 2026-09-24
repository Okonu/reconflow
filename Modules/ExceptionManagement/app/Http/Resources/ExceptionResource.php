<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Http\Resources;

use App\Support\BusinessCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ExceptionManagement\Models\ReconException;

final class ExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $e = $this->resource;
        assert($e instanceof ReconException);
        $latestDate = BusinessCalendar::latestClosedDate();

        return [
            'id' => $e->id,
            'business_date' => $e->business_date->toDateString(),
            'carried_from' => $e->business_date->toDateString() < $latestDate && ! $e->state->isClosed() ? $e->business_date->toDateString() : null,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'category' => $e->category,
            'severity' => $e->severity->value,
            'amount_at_risk' => (string) $e->amount_at_risk,
            'transaction_id' => $e->transaction_id,
            'payment_ids' => $e->payment_ids,
            'region' => $e->region,
            'owner' => $e->owner === null ? null : ['id' => $e->owner->getKey(), 'name' => $e->owner->getAttribute('name')],
            'due_at' => $e->due_at?->toIso8601String(),
            'overdue' => $e->isOverdue(),
            'state' => $e->state->value,
            'state_label' => $e->state->label(),
            'soft' => $e->soft,
            'needs_review' => $e->needs_review,
            'resolution' => $e->resolution,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
