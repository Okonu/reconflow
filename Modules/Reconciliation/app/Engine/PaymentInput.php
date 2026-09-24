<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final readonly class PaymentInput
{
    public function __construct(
        public string $key,
        public string $paymentId,
        public int $paidAt,
        public ?string $phone,
        public int $amountCents,
        public ?string $reference,
        public string $ownDate,
        public ?int $recordId = null,
    ) {}

    public function identity(): string
    {
        return implode('|', [$this->paymentId, $this->paidAt, $this->amountCents, $this->reference ?? '']);
    }
}
