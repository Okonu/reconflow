<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Listeners;

use Modules\ExceptionManagement\Services\ExceptionSynchronizer;
use Modules\Reconciliation\Events\FuzzyMatchRejected;

final class SplitRejectedFuzzyMatch
{
    public function __construct(private readonly ExceptionSynchronizer $sync) {}

    public function handle(FuzzyMatchRejected $event): void
    {
        $this->sync->onFuzzyRejected($event->resultId, $event->reason);
    }
}
