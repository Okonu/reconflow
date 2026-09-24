<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final readonly class PostingLine
{
    public function __construct(
        public string $journalId,
        public string $transactionId,
        public int $amountCents,
    ) {}
}
