<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final readonly class EngineOutput
{
    public function __construct(
        public array $items,
        public array $escalations,
    ) {}
}
