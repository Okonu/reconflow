<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use App\Contracts\AuditActor;
use App\Exceptions\DomainException;
use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Models\ExceptionEvent;
use Modules\ExceptionManagement\Models\ReconException;

final class ExceptionWorkflow
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function transition(ReconException $exception, ExceptionState $to, AuditActor|string|null $actor, string $comment = '', array $data = []): ReconException
    {
        $from = $exception->state;
        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw DomainException::conflict("An exception that is {$from->label()} cannot move to {$to->label()}.");
        }
        $exception->fill([
            'state' => $to,
            'resolved_at' => $to->isClosed() ? now() : null,
            'resolution' => $to->isClosed() ? ($comment !== '' ? $comment : $to->label()) : $exception->resolution,
        ])->save();
        $this->event($exception, $actor, 'transition', $comment, $data, $from, $to);
        $this->audit->record(ExceptionAuditAction::Transitioned, $actor, 'exception', $exception->id, [
            'from' => $from->value,
            'to' => $to->value,
            'comment' => $comment,
            ...$data,
        ]);

        return $exception;
    }

    public function event(ReconException $exception, AuditActor|string|null $actor, string $type, string $comment = '', array $data = [], ?ExceptionState $from = null, ?ExceptionState $to = null): ExceptionEvent
    {
        return ExceptionEvent::query()->create([
            'exception_id' => $exception->id,
            'actor_id' => $actor instanceof AuditActor ? $actor->auditActorId() : null,
            'actor_label' => $actor instanceof AuditActor ? $actor->auditActorLabel() : ($actor ?? AuditLogger::SYSTEM_ACTOR),
            'type' => $type,
            'from_state' => $from?->value,
            'to_state' => $to?->value,
            'comment' => $comment,
            'data' => $data,
        ]);
    }
}
