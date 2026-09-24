<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\PaymentInput;
use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;

final class DetectDuplicatePayments
{
    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        $next = clone $state;
        $window = $input->config->duplicateWindowSeconds();
        $knownTransactions = $this->knownTransactions($state);

        $ordered = array_values($state->payments);
        $position = array_flip(array_keys($state->payments));
        usort($ordered, fn (PaymentInput $a, PaymentInput $b): int => [$a->paidAt, $position[$a->key]] <=> [$b->paidAt, $position[$b->key]]);

        $seenReceipts = [];
        $lastByReferenceAmount = [];
        foreach ($ordered as $payment) {
            $original = $seenReceipts[$payment->paymentId] ?? null;
            $referenceKey = $payment->reference === null ? null : $payment->reference.'|'.$payment->amountCents;
            if ($original === null && $referenceKey !== null && isset($lastByReferenceAmount[$referenceKey])) {
                $earlier = $lastByReferenceAmount[$referenceKey];
                if ($payment->paidAt - $earlier->paidAt <= $window) {
                    $original = $earlier;
                }
            }

            if ($original !== null) {
                unset($next->payments[$payment->key]);
                $next->items[] = new ResultItem(
                    section: ResultSection::Current,
                    status: ReconStatus::DuplicatePayment,
                    ruleId: 'R5',
                    payments: [$payment],
                    flags: [
                        'transaction_id' => isset($knownTransactions[$payment->reference ?? '']) ? $payment->reference : null,
                        'duplicate_of' => $original->paymentId,
                        'same_receipt' => $original->paymentId === $payment->paymentId,
                    ],
                );

                continue;
            }

            $seenReceipts[$payment->paymentId] = $payment;
            if ($referenceKey !== null) {
                $lastByReferenceAmount[$referenceKey] = $payment;
            }
        }

        return $next;
    }

    private function knownTransactions(MatchState $state): array
    {
        $known = [];
        foreach ([...$state->sales, ...$state->priorItems] as $sale) {
            $known[$sale->transactionId] = true;
        }

        return $known;
    }
}
