<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\ExceptionManagement\Models\ReconException;

final class ExceptionOpened
{
    use Dispatchable;

    public function __construct(public readonly ReconException $exception) {}
}
