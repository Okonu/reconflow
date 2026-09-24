<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\Pair;

final class MatchByReference
{
    public function __construct(private readonly bool $priorItems = false) {}

    public static function currentDay(): self
    {
        return new self(false);
    }

    public static function priorDay(): self
    {
        return new self(true);
    }

    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        $next = clone $state;
        $byReference = [];
        foreach ($state->payments as $key => $payment) {
            if ($payment->reference !== null) {
                $byReference[$payment->reference][] = $key;
            }
        }

        $candidates = $this->priorItems ? $state->priorItems : $state->sales;
        foreach ($candidates as $saleKey => $sale) {
            $paymentKeys = $byReference[$sale->transactionId] ?? [];
            if ($paymentKeys === []) {
                continue;
            }
            $payments = array_map(fn (string $k) => $next->payments[$k], $paymentKeys);
            foreach ($paymentKeys as $k) {
                unset($next->payments[$k]);
            }
            unset($byReference[$sale->transactionId]);
            $pair = new Pair($sale, $payments, count($payments) === 1 ? 'R1' : 'R2', '1.000');

            if ($this->priorItems) {
                unset($next->priorItems[$saleKey]);
                $next->priorPairs[] = $pair;
            } else {
                unset($next->sales[$saleKey]);
                $next->pairs[] = $pair;
            }
        }

        return $next;
    }
}
