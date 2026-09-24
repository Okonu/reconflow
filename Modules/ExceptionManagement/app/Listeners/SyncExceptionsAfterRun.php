<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Listeners;

use Modules\ExceptionManagement\Services\ExceptionSynchronizer;
use Modules\Reconciliation\Events\RunFinished;

final class SyncExceptionsAfterRun
{
    public function __construct(private readonly ExceptionSynchronizer $sync) {}

    public function handle(RunFinished $event): void
    {
        $this->sync->sync($event->run);
    }
}
