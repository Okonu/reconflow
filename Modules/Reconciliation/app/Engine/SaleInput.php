<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final readonly class SaleInput
{
    public const CURRENT = 'current';

    public const CARRIED = 'carried';

    public const LOOKBACK = 'lookback';

    public function __construct(
        public string $key,
        public string $transactionId,
        public int $soldAt,
        public string $phone,
        public int $expectedCents,
        public ?string $reference,
        public string $origin = self::CURRENT,
        public ?int $recordId = null,
        public ?int $priorResultId = null,
        public ?string $priorDate = null,
    ) {}

    public function isPrior(): bool
    {
        return $this->origin !== self::CURRENT;
    }
}
