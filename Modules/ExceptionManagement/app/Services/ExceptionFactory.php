<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use App\Contracts\AuditActor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Enums\StatusFamily;
use Modules\ExceptionManagement\Events\ExceptionOpened;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Models\ReconResult;

final class ExceptionFactory
{
    public function __construct(
        private readonly ExceptionClassifier $classifier,
        private readonly OwnerAssigner $assigner,
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function open(ReconResult $result, ReconStatus $status, AuditActor|string|null $actor = null, ?int $predecessorId = null, ?array $paymentOnly = null): ReconException
    {
        $family = StatusFamily::of($status) ?? StatusFamily::Unmatched;
        $identity = $paymentOnly === null ? ExceptionKey::identity($result) : $result->business_date->toDateString().'|payment|'.implode(',', $paymentOnly['identities']).'|';
        $value = $this->classifier->valueAtRisk($result, $status);
        $severity = $this->classifier->severity($status, $value);
        $soft = $status === ReconStatus::PendingTiming;
        $region = $result->sale_record_id === null ? null : DB::table('sales_records')->where('id', $result->sale_record_id)->value('region');
        $now = CarbonImmutable::now();

        $exception = ReconException::query()->create([
            'key' => ExceptionKey::key($identity, $family),
            'identity' => $identity,
            'business_date' => $result->business_date->toDateString(),
            'result_id' => $result->id,
            'run_id' => $result->run_id,
            'section' => $result->section->value,
            'status' => $status,
            'family' => $family->value,
            'category' => $this->classifier->category($status, (array) $result->flags)->value,
            'severity' => $severity,
            'amount_at_risk' => $value,
            'transaction_id' => $paymentOnly === null ? $result->transaction_id : null,
            'payment_ids' => $paymentOnly['payment_ids'] ?? $result->payment_ids,
            'region' => $region,
            'owner_id' => $soft ? null : $this->assigner->assign($region),
            'due_at' => $this->classifier->dueAt($status, $severity, $now),
            'state' => ExceptionState::Open,
            'soft' => $soft,
            'predecessor_id' => $predecessorId,
        ]);

        $this->workflow->event($exception, $actor, 'opened', '', ['status' => $status->value, 'severity' => $severity->value, 'run_id' => $result->run_id]);
        $this->audit->record(ExceptionAuditAction::Opened, $actor, 'exception', $exception->id, [
            'business_date' => $exception->business_date->toDateString(),
            'status' => $status->value,
            'category' => $exception->category,
            'severity' => $severity->value,
            'amount_at_risk' => (string) $value,
            'owner_id' => $exception->owner_id,
            'predecessor_id' => $predecessorId,
        ]);
        ExceptionOpened::dispatch($exception);

        return $exception;
    }

    public function reclassify(ReconException $exception, ReconResult $result, ReconStatus $status, string $reason, bool $escalation = false): ReconException
    {
        $value = $this->classifier->valueAtRisk($result, $status);
        $severity = $this->classifier->severity($status, $value);
        $wasSoft = $exception->soft;
        $family = StatusFamily::of($status) ?? StatusFamily::Unmatched;
        $exception->fill([
            'status' => $status,
            'family' => $family->value,
            'key' => ExceptionKey::key($exception->identity, $family),
            'category' => $this->classifier->category($status, (array) $result->flags)->value,
            'severity' => $severity,
            'amount_at_risk' => $value,
            'soft' => $status === ReconStatus::PendingTiming,
            'owner_id' => $exception->owner_id ?? ($status === ReconStatus::PendingTiming ? null : $this->assigner->assign($exception->region)),
            'due_at' => ($escalation || $wasSoft || $exception->due_at === null) ? $this->classifier->dueAt($status, $severity, CarbonImmutable::now()) : $exception->due_at,
            'escalated_at' => $escalation ? now() : $exception->escalated_at,
        ])->save();
        if ($exception->state->isClosed()) {
            $this->workflow->transition($exception, ExceptionState::Open, AuditLogger::SYSTEM_ACTOR, $reason);
        }
        $this->workflow->event($exception, AuditLogger::SYSTEM_ACTOR, $escalation ? 'escalated' : 'reclassified', $reason, ['status' => $status->value, 'severity' => $severity->value]);
        $this->audit->record(ExceptionAuditAction::Reclassified, AuditLogger::SYSTEM_ACTOR, 'exception', $exception->id, [
            'status' => $status->value,
            'severity' => $severity->value,
            'escalation' => $escalation,
            'reason' => $reason,
        ]);
        if ($escalation) {
            ExceptionOpened::dispatch($exception);
        }

        return $exception;
    }
}
