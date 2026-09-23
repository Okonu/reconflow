<?php

declare(strict_types=1);

namespace App\Contracts;

interface ResetsDemoData
{
    public function resetOrder(): int;

    public function truncateOperationalData(): array;
}
