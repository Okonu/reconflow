<?php

declare(strict_types=1);

namespace Modules\Reconciliation\DTOs;

use Modules\Reconciliation\Enums\RunTrigger;

final readonly class RunRequest
{
    public function __construct(
        public string $businessDate,
        public RunTrigger $trigger,
        public bool $refreshFromSources = false,
        public array $replaceManualSources = [],
    ) {}
}
