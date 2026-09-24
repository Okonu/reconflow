<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Actions;

use Modules\Audit\Services\AuditLogger;
use Modules\ExceptionManagement\Enums\ExceptionAuditAction;
use Modules\ExceptionManagement\Enums\ExceptionState;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Services\ExceptionWorkflow;
use Modules\Users\Models\User;

final class WorkException
{
    public function __construct(
        private readonly ExceptionWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function startReview(User $user, ReconException $exception): ReconException
    {
        return $this->workflow->transition($exception, ExceptionState::InReview, $user);
    }

    public function resolveNoAction(User $user, ReconException $exception, string $reason): ReconException
    {
        return $this->workflow->transition($exception, ExceptionState::ResolvedNoAction, $user, $reason);
    }

    public function comment(User $user, ReconException $exception, string $comment): void
    {
        $this->workflow->event($exception, $user, 'comment', $comment);
        $this->audit->record(ExceptionAuditAction::Commented, $user, 'exception', $exception->id, ['length' => mb_strlen($comment)]);
    }

    public function assign(User $user, array $exceptionIds, ?int $ownerId): int
    {
        $exceptions = ReconException::query()->whereKey($exceptionIds)->get();
        foreach ($exceptions as $exception) {
            $previous = $exception->owner_id;
            $exception->update(['owner_id' => $ownerId]);
            $this->workflow->event($exception, $user, 'assigned', '', ['from' => $previous, 'to' => $ownerId]);
            $this->audit->record(ExceptionAuditAction::Assigned, $user, 'exception', $exception->id, ['from' => $previous, 'to' => $ownerId]);
        }

        return $exceptions->count();
    }
}
