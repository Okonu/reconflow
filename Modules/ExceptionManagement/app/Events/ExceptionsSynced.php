<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Reconciliation\Models\ReconRun;

final class ExceptionsSynced
{
    use Dispatchable;

    public function __construct(public readonly ReconRun $run, public readonly array $openedIds) {}
}
