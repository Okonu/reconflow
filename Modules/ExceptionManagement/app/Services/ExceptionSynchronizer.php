<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Enums\StatusFamily;
use Modules\ExceptionManagement\Events\ExceptionsSynced;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;

final class ExceptionSynchronizer
{
    public function __construct(
        private readonly ExceptionFactory $factory,
        private readonly ExceptionWorkflow $workflow,
        private readonly ExceptionClassifier $classifier,
        private readonly AuditLogger $audit,
    ) {}

    public function sync(ReconRun $run): void
    {
        if ($run->status !== RunStatus::Completed || $run->superseded_by_id !== null) {
            return;
        }
        $date = $run->business_date->toDateString();
        $existing = ReconException::query()->whereDate('business_date', $date)->orderBy('id')->get()->keyBy('key');
        $current = [];
        $identityStatus = [];
        $opened = [];

        $results = ReconResult::query()->where('run_id', $run->id)->where('section', ResultSection::Current->value)->orderBy('id')->get();
        foreach ($results as $result) {
            $identityStatus[ExceptionKey::identity($result)] = $result->status;
            $family = StatusFamily::of($result->status);
            if ($family === null) {
                continue;
            }
            $key = ExceptionKey::key(ExceptionKey::identity($result), $family);
            $current[$key] = true;
            $match = $existing->get($key);
            if ($match !== null) {
                $this->relink($match, $result, $run);

                continue;
            }
            $predecessor = $existing->first(fn (ReconException $e) => $e->identity === ExceptionKey::identity($result) && ! $e->state->isClosed());
            if ($predecessor !== null) {
                $this->close($predecessor, "Superseded by re-run v{$run->version}: now {$result->status->value}");
            }
            $opened[] = $this->factory->open($result, $result->status, AuditLogger::SYSTEM_ACTOR, $predecessor?->id)->id;
        }

        foreach ($existing as $key => $exception) {
            if (isset($current[$key]) || $exception->state->isClosed() || $exception->run_id === $run->id) {
                continue;
            }
            $now = $identityStatus[$exception->identity] ?? null;
            $reason = "Resolved by re-run v{$run->version}".($now === null ? '' : " (now {$now->value})");
            if ($exception->state->hasAdjustmentInFlight()) {
                if (! $exception->needs_review) {
                    $exception->update(['needs_review' => true]);
                    $this->workflow->event($exception, AuditLogger::SYSTEM_ACTOR, 'needs_review', "Needs review: underlying result changed in re-run v{$run->version}");
                    $this->audit->record(ExceptionAuditAction::NeedsReview, AuditLogger::SYSTEM_ACTOR, 'exception', $exception->id, ['run_id' => $run->id]);
                }

                continue;
            }
            $this->close($exception, $reason);
        }

        ExceptionsSynced::dispatch($run, $opened);
    }

    private function relink(ReconException $exception, ReconResult $result, ReconRun $run): void
    {
        if ($exception->result_id === $result->id) {
            return;
        }
        $statusChanged = $exception->status !== $result->status;
        $exception->fill(['result_id' => $result->id, 'run_id' => $run->id, 'status' => $result->status]);
        if ($statusChanged) {
            $exception->category = $this->classifier->category($result->status, (array) $result->flags)->value;
        }
        $exception->save();
        $this->workflow->event($exception, AuditLogger::SYSTEM_ACTOR, 'relinked', "Re-linked to run v{$run->version}", ['run_id' => $run->id, 'result_id' => $result->id]);
        $this->audit->record(ExceptionAuditAction::Relinked, AuditLogger::SYSTEM_ACTOR, 'exception', $exception->id, [
            'run_id' => $run->id,
            'version' => $run->version,
            'result_id' => $result->id,
        ]);
    }

    public function close(ReconException $exception, string $reason): void
    {
        if ($exception->state->isClosed()) {
            return;
        }
        $exception->forceFill(['state' => ExceptionState::Resolved, 'resolution' => $reason, 'resolved_at' => now()])->save();
        $this->workflow->event($exception, AuditLogger::SYSTEM_ACTOR, 'auto_resolved', $reason);
        $this->audit->record(ExceptionAuditAction::AutoResolved, AuditLogger::SYSTEM_ACTOR, 'exception', $exception->id, ['reason' => $reason]);
    }

    public function onItemState(int $resultId, string $state, ReconStatus $effective, string $reason): void
    {
        $result = ReconResult::query()->find($resultId);
        if ($result === null) {
            return;
        }
        $exception = ReconException::query()->where('result_id', $resultId)->latest('id')->first()
            ?? ReconException::query()->where('identity', ExceptionKey::identity($result))->latest('id')->first();

        if ($state === 'resolved') {
            if ($exception === null) {
                return;
            }
            if ($exception->state->hasAdjustmentInFlight()) {
                $exception->update(['needs_review' => true]);
                $this->workflow->event($exception, AuditLogger::SYSTEM_ACTOR, 'needs_review', $reason);

                return;
            }
            $this->close($exception, $reason);

            return;
        }

        if ($exception === null) {
            $this->factory->open($result, $effective, AuditLogger::SYSTEM_ACTOR);

            return;
        }
        $this->factory->reclassify($exception, $result, $effective, $reason, $state === 'escalated');
    }

    public function onFuzzyRejected(int $resultId, string $reason): void
    {
        $result = ReconResult::query()->find($resultId);
        if ($result === null || $result->section !== ResultSection::Current) {
            return;
        }
        $this->factory->open($result, ReconStatus::UnmatchedPayment, AuditLogger::SYSTEM_ACTOR, null, [
            'identities' => (array) $result->payment_identities,
            'payment_ids' => (array) $result->payment_ids,
        ]);
    }
}
