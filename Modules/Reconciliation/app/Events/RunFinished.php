<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Reconciliation\Models\ReconRun;

final class RunFinished
{
    use Dispatchable;

    public function __construct(public readonly ReconRun $run) {}
}
