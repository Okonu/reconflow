<?php

declare(strict_types=1);

namespace App\Contracts;

interface BusinessDateLock
{
    public function isLocked(string $businessDate): bool;
}
