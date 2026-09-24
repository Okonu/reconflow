<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

final class MatchState
{
    public array $sales = [];

    public array $priorItems = [];

    public array $payments = [];

    public array $pairs = [];

    public array $priorPairs = [];

    public array $items = [];

    public array $tiedSales = [];

    public array $tiedPayments = [];

    public static function from(EngineInput $input): self
    {
        $state = new self;
        foreach ($input->sales as $sale) {
            $state->sales[$sale->key] = $sale;
        }
        foreach ($input->priorItems as $sale) {
            $state->priorItems[$sale->key] = $sale;
        }
        foreach ($input->payments as $payment) {
            $state->payments[$payment->key] = $payment;
        }

        return $state;
    }
}
