<?php

declare(strict_types=1);

namespace Modules\Adjustments\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Adjustments\Contracts\ErpClient;
use Modules\Adjustments\Enums\AdjustmentAuditAction;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Models\Adjustment;
use Modules\Adjustments\Models\ErpPostingOut;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;

final class PostAdjustment
{
    public function __construct(
        private readonly ErpClient $erp,
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Adjustment $adjustment): Adjustment
    {
        return DB::transaction(function () use ($adjustment): Adjustment {
            $locked = Adjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if (! in_array($locked->state, [AdjustmentState::Approved, AdjustmentState::PostingFailed], true)) {
                return $locked;
            }
            $request = ['idempotency_key' => $locked->idempotency_key, ...$locked->journal];
            $response = $this->erp->postJournal($request);
            $locked->increment('posting_attempts');
            ErpPostingOut::query()->create([
                'adjustment_id' => $locked->id,
                'idempotency_key' => $locked->idempotency_key,
                'request' => $request,
                'response' => $response->body,
                'http_status' => $response->httpStatus,
                'succeeded' => $response->succeeded,
                'error' => $response->error,
            ]);
            $exception = $locked->exceptionRecord();

            if (! $response->succeeded) {
                $locked->update(['state' => AdjustmentState::PostingFailed]);
                $this->workflow->transition($exception, ExceptionState::PostingFailed, AuditLogger::SYSTEM_ACTOR, (string) $response->error, ['adjustment_id' => $locked->id]);
                $this->audit->record(AdjustmentAuditAction::PostingFailed, AuditLogger::SYSTEM_ACTOR, 'adjustment', $locked->id, [
                    'idempotency_key' => $locked->idempotency_key,
                    'http_status' => $response->httpStatus,
                    'error' => $response->error,
                ]);

                return $locked;
            }

            $journalId = (string) ($response->body['journal_id'] ?? '');
            $locked->update(['state' => AdjustmentState::Posted, 'erp_journal_id' => $journalId, 'posted_at' => now()]);
            $this->workflow->transition($exception, ExceptionState::Posted, AuditLogger::SYSTEM_ACTOR, "Posted to ERP as {$journalId}", ['adjustment_id' => $locked->id, 'journal_id' => $journalId]);
            $this->workflow->transition($exception, ExceptionState::Resolved, AuditLogger::SYSTEM_ACTOR, "Adjustment posted to ERP as {$journalId}", ['adjustment_id' => $locked->id]);
            $this->audit->record(AdjustmentAuditAction::Posted, AuditLogger::SYSTEM_ACTOR, 'adjustment', $locked->id, [
                'idempotency_key' => $locked->idempotency_key,
                'journal_id' => $journalId,
                'replayed' => (bool) ($response->body['replayed'] ?? false),
            ]);

            return $locked;
        });
    }
}
