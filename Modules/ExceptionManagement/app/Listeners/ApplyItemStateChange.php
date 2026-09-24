<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Listeners;

use Modules\ExceptionManagement\Services\ExceptionSynchronizer;
use Modules\Reconciliation\Events\ItemStateChanged;

final class ApplyItemStateChange
{
    public function __construct(private readonly ExceptionSynchronizer $sync) {}

    public function handle(ItemStateChanged $event): void
    {
        $this->sync->onItemState($event->resultId, $event->state->value, $event->effectiveStatus, $event->reason);
    }
}
