<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Reconciliation\Actions\ExecuteRun;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Users\Models\User;

final class ExecuteRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly int $runId,
        public readonly RunRequest $request,
        public readonly ?int $actorId = null,
    ) {}

    public function handle(ExecuteRun $execute): void
    {
        $run = ReconRun::query()->findOrFail($this->runId);
        $actor = $this->actorId === null ? null : User::query()->find($this->actorId);
        $execute->handle($run, $this->request, $actor);
    }
}
