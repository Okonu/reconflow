<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;

final readonly class ResultItem
{
    public function __construct(
        public ResultSection $section,
        public ReconStatus $status,
        public string $ruleId,
        public ?SaleInput $sale = null,
        public array $payments = [],
        public ?int $postedCents = null,
        public ?string $journalId = null,
        public ?string $confidence = null,
        public ?string $tag = null,
        public array $flags = [],
    ) {}

    public function transactionId(): ?string
    {
        return $this->sale !== null ? $this->sale->transactionId : ($this->flags['transaction_id'] ?? null);
    }

    public function expectedCents(): ?int
    {
        return $this->sale?->expectedCents;
    }

    public function actualCents(): ?int
    {
        return $this->payments === [] ? null : array_sum(array_map(fn (PaymentInput $p): int => $p->amountCents, $this->payments));
    }

    public function varianceCents(): ?int
    {
        $expected = $this->expectedCents();
        $actual = $this->actualCents();

        return $expected === null || $actual === null ? null : $actual - $expected;
    }

    public function paymentIds(): array
    {
        return array_map(fn (PaymentInput $p): string => $p->paymentId, $this->payments);
    }
}
