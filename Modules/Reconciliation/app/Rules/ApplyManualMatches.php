<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\Pair;

final class ApplyManualMatches
{
    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        if ($input->manualMatches === []) {
            return $state;
        }
        $next = clone $state;
        foreach ($state->payments as $key => $payment) {
            $sale = $input->manualMatches[$payment->identity()] ?? null;
            if ($sale === null) {
                continue;
            }
            $next->priorPairs[] = new Pair($sale, [$payment], Pair::MANUAL, '1.000');
            unset($next->payments[$key]);
        }

        return $next;
    }
}
