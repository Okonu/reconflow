<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Support;

use App\Contracts\BusinessDateLock;
use Modules\ExceptionManagement\Models\RunSignoff;

final class SignoffDateLock implements BusinessDateLock
{
    public function isLocked(string $businessDate): bool
    {
        return RunSignoff::query()->activeFor($businessDate)->exists();
    }
}
