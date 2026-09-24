<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final readonly class Pair
{
    public const MANUAL = 'MANUAL';

    public function __construct(
        public SaleInput $sale,
        public array $payments,
        public string $rule,
        public ?string $confidence = null,
    ) {}

    public function actualCents(): int
    {
        return array_sum(array_map(fn (PaymentInput $p): int => $p->amountCents, $this->payments));
    }
}
