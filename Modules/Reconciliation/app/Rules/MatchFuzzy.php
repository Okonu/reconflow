<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\Pair;
use Modules\Reconciliation\Engine\PaymentInput;
use Modules\Reconciliation\Engine\SaleInput;

final class MatchFuzzy
{
    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        $next = clone $state;
        $tolerance = $input->config->toleranceCents();
        $window = $input->config->fuzzyWindowSeconds();

        $byPhone = [];
        foreach ($state->payments as $key => $payment) {
            if ($payment->phone !== null) {
                $byPhone[$payment->phone][] = $key;
            }
        }

        $saleCandidates = [];
        $paymentDegree = [];
        $carried = array_filter($state->priorItems, fn (SaleInput $s): bool => $s->origin === SaleInput::CARRIED);
        foreach ([...$state->sales, ...$carried] as $saleKey => $sale) {
            foreach ($byPhone[$sale->phone] ?? [] as $paymentKey) {
                $payment = $state->payments[$paymentKey];
                if (abs($payment->amountCents - $sale->expectedCents) <= $tolerance && abs($payment->paidAt - $sale->soldAt) <= $window) {
                    $saleCandidates[$saleKey][] = $paymentKey;
                    $paymentDegree[$paymentKey] = ($paymentDegree[$paymentKey] ?? 0) + 1;
                }
            }
        }

        foreach ($saleCandidates as $saleKey => $paymentKeys) {
            $paymentKey = $paymentKeys[0];
            if (count($paymentKeys) === 1 && $paymentDegree[$paymentKey] === 1) {
                $sale = $state->sales[$saleKey] ?? $state->priorItems[$saleKey];
                $payment = $state->payments[$paymentKey];
                $pair = new Pair($sale, [$payment], 'R3', $this->confidence($sale, $payment, $tolerance, $window));
                if ($sale->isPrior()) {
                    $next->priorPairs[] = $pair;
                    unset($next->priorItems[$saleKey]);
                } else {
                    $next->pairs[] = $pair;
                    unset($next->sales[$saleKey]);
                }
                unset($next->payments[$paymentKey]);

                continue;
            }
            $next->tiedSales[$saleKey] = true;
            foreach ($paymentKeys as $key) {
                $next->tiedPayments[$key] = true;
            }
        }

        return $next;
    }

    private function confidence(SaleInput $sale, PaymentInput $payment, int $tolerance, int $window): string
    {
        $timePenalty = $window > 0 ? intdiv(400 * abs($payment->paidAt - $sale->soldAt), $window) : 0;
        $amountPenalty = $tolerance > 0 ? intdiv(200 * abs($payment->amountCents - $sale->expectedCents), $tolerance) : 0;
        $score = max(400, 1000 - $timePenalty - $amountPenalty);

        return sprintf('%d.%03d', intdiv($score, 1000), $score % 1000);
    }
}
