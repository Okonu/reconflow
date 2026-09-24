<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Actions;

use App\Contracts\AuditActor;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Models\ReconRun;

final class ReconcileDate
{
    public function __construct(
        private readonly QueueRun $queue,
        private readonly ExecuteRun $execute,
    ) {}

    public function handle(RunRequest $request, AuditActor|string|null $actor = null): ReconRun
    {
        return $this->execute->handle($this->queue->handle($request, $actor), $request, $actor);
    }
}
