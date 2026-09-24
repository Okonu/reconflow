<?php

declare(strict_types=1);

namespace Modules\Adjustments\Actions;

use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Adjustments\DTOs\AdjustmentProposal;
use Modules\Adjustments\Enums\AdjustmentAuditAction;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Enums\AdjustmentType;
use Modules\Adjustments\Models\Adjustment;
use Modules\Adjustments\Services\JournalBuilder;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;
use Modules\Users\Models\User;

final class ProposeAdjustment
{
    public function __construct(
        private readonly JournalBuilder $journals,
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, ReconException $exception, AdjustmentProposal $proposal): Adjustment
    {
        $overPaid = $exception->resultRecord()?->variance?->isPositive() ?? false;
        if (! in_array($proposal->type, AdjustmentType::for($exception->status, $overPaid), true)) {
            throw DomainException::invalid("A {$proposal->type->label()} adjustment does not apply to a {$exception->status->value} exception.");
        }
        if (! in_array($exception->state, [ExceptionState::Open, ExceptionState::InReview], true)) {
            throw DomainException::conflict("An exception that is {$exception->state->label()} cannot take a new adjustment.");
        }

        return DB::transaction(function () use ($user, $exception, $proposal): Adjustment {
            if ($exception->state === ExceptionState::Open) {
                $this->workflow->transition($exception, ExceptionState::InReview, $user);
            }
            $amount = Money::of($proposal->amount);
            $adjustment = Adjustment::query()->create([
                'exception_id' => $exception->id,
                'type' => $proposal->type,
                'amount' => $amount,
                'reason' => $proposal->reason,
                'proposed_by' => $user->id,
                'state' => AdjustmentState::PendingApproval,
                'high_value' => $amount->isGreaterThan(Money::of((string) config('adjustments.approval_threshold'))),
                'idempotency_key' => (string) Str::uuid(),
                'journal' => $this->journals->build($proposal->type, $exception, $amount),
            ]);
            $this->workflow->transition($exception, ExceptionState::PendingApproval, $user, $proposal->reason, ['adjustment_id' => $adjustment->id, 'event' => 'ADJUSTMENT_PROPOSED']);
            $this->audit->record(AdjustmentAuditAction::Proposed, $user, 'adjustment', $adjustment->id, [
                'exception_id' => $exception->id,
                'type' => $proposal->type->value,
                'amount' => (string) $amount,
                'high_value' => $adjustment->high_value,
                'idempotency_key' => $adjustment->idempotency_key,
                'reason' => $proposal->reason,
            ]);

            return $adjustment;
        });
    }
}
