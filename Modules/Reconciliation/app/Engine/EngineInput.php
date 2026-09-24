<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

use Modules\Reconciliation\DTOs\RuleConfig;

final readonly class EngineInput
{
    public function __construct(
        public string $businessDate,
        public array $sales,
        public array $priorItems,
        public array $payments,
        public array $postingsByTransaction,
        public int $timingCutoffAt,
        public int $dayEndsAt,
        public RuleConfig $config,
        public array $manualMatches = [],
        public array $rejectedPairs = [],
    ) {}
}
