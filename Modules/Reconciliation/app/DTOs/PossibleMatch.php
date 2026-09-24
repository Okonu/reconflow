<?php

declare(strict_types=1);

namespace Modules\Reconciliation\DTOs;

final readonly class PossibleMatch
{
    public function __construct(
        public int $paymentResultId,
        public int $paymentRecordId,
        public string $paymentId,
        public string $paymentDate,
        public string $paidAt,
        public string $amount,
        public ?string $reference,
        public string $identity,
        public int $daysLate,
    ) {}
}
