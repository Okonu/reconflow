<?php

declare(strict_types=1);

namespace Modules\Adjustments\DTOs;

use Modules\Adjustments\Enums\AdjustmentType;

final readonly class AdjustmentProposal
{
    public function __construct(
        public AdjustmentType $type,
        public string $amount,
        public string $reason,
    ) {}
}
