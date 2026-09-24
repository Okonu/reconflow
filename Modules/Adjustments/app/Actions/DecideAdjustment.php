<?php

declare(strict_types=1);

namespace Modules\Adjustments\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustments\Enums\AdjustmentAuditAction;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Jobs\PostAdjustmentJob;
use Modules\Adjustments\Models\Adjustment;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;
use Modules\Users\Models\User;

final class DecideAdjustment
{
    public function __construct(
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(User $user, Adjustment $adjustment, string $comment = ''): Adjustment
    {
        Gate::forUser($user)->authorize('approve', $adjustment);

        DB::transaction(function () use ($user, $adjustment, $comment): void {
            $locked = Adjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            Gate::forUser($user)->authorize('approve', $locked);
            $locked->update(['state' => AdjustmentState::Approved, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_comment' => $comment]);
            $exception = $locked->exceptionRecord();
            $this->workflow->transition($exception, ExceptionState::Approved, $user, $comment, ['adjustment_id' => $locked->id]);
            $this->audit->record(AdjustmentAuditAction::Approved, $user, 'adjustment', $locked->id, [
                'exception_id' => $exception->id,
                'amount' => (string) $locked->amount,
                'proposed_by' => $locked->proposed_by,
                'comment' => $comment,
            ]);
        });
        PostAdjustmentJob::dispatch($adjustment->id);

        return $adjustment->fresh() ?? $adjustment;
    }

    public function reject(User $user, Adjustment $adjustment, string $comment): Adjustment
    {
        Gate::forUser($user)->authorize('reject', $adjustment);

        return DB::transaction(function () use ($user, $adjustment, $comment): Adjustment {
            $adjustment->update(['state' => AdjustmentState::Rejected, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_comment' => $comment]);
            $exception = $adjustment->exceptionRecord();
            $this->workflow->transition($exception, ExceptionState::InReview, $user, $comment, ['adjustment_id' => $adjustment->id, 'event' => 'REJECTED']);
            $this->audit->record(AdjustmentAuditAction::Rejected, $user, 'adjustment', $adjustment->id, [
                'exception_id' => $exception->id,
                'comment' => $comment,
            ]);

            return $adjustment;
        });
    }
}
