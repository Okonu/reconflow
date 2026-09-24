<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\BusinessDateLock;

final class NoDateLock implements BusinessDateLock
{
    public function isLocked(string $businessDate): bool
    {
        return false;
    }
}
