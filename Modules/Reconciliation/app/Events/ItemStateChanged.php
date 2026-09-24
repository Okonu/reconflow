<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Reconciliation\Enums\ItemState;
use Modules\Reconciliation\Enums\ReconStatus;

final class ItemStateChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $resultId,
        public readonly ItemState $state,
        public readonly ReconStatus $effectiveStatus,
        public readonly string $reason,
    ) {}
}
