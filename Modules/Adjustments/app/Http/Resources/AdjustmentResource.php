<?php

declare(strict_types=1);

namespace Modules\Adjustments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustments\Models\Adjustment;
use Modules\ExceptionManagement\Models\ReconException;

final class AdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $a = $this->resource;
        assert($a instanceof Adjustment);
        $user = $request->user();

        return [
            'id' => $a->id,
            'exception_id' => $a->exception_id,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'amount' => (string) $a->amount,
            'reason' => $a->reason,
            'state' => $a->state->value,
            'state_label' => $a->state->label(),
            'high_value' => $a->high_value,
            'idempotency_key' => $a->idempotency_key,
            'journal' => $a->journal,
            'erp_journal_id' => $a->erp_journal_id,
            'posting_attempts' => $a->posting_attempts,
            'proposed_by' => $a->proposer?->getAttribute('name'),
            'decided_by' => $a->decider?->getAttribute('name'),
            'decision_comment' => $a->decision_comment,
            'created_at' => $a->created_at?->toIso8601String(),
            'posted_at' => $a->posted_at?->toIso8601String(),
            'exception' => $a->relationLoaded('exception') ? $this->exceptionSummary($a->exceptionRecord()) : null,
            'can' => [
                'approve' => $user?->can('approve', $a) ?? false,
                'reject' => $user?->can('reject', $a) ?? false,
                'retry' => $user?->can('retry', $a) ?? false,
            ],
            'why_not' => $user === null ? null : Gate::forUser($user)->inspect('approve', $a)->message(),
        ];
    }

    private function exceptionSummary(ReconException $exception): array
    {
        return [
            'business_date' => $exception->business_date->toDateString(),
            'status' => $exception->status->value,
            'transaction_id' => $exception->transaction_id,
            'category' => $exception->category,
        ];
    }
}
