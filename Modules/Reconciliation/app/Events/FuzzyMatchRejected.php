<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class FuzzyMatchRejected
{
    use Dispatchable;

    public function __construct(
        public readonly int $resultId,
        public readonly string $reason,
    ) {}
}
