<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Engine;

use Modules\Reconciliation\Rules\ApplyManualMatches;
use Modules\Reconciliation\Rules\ClassifyLeftovers;
use Modules\Reconciliation\Rules\DetectDuplicatePayments;
use Modules\Reconciliation\Rules\EvaluatePairs;
use Modules\Reconciliation\Rules\MatchByReference;
use Modules\Reconciliation\Rules\MatchFuzzy;

final class ReconciliationEngine
{
    public function reconcile(EngineInput $input): EngineOutput
    {
        $state = MatchState::from($input);

        foreach ([
            new DetectDuplicatePayments,
            new ApplyManualMatches,
            MatchByReference::currentDay(),
            MatchByReference::priorDay(),
            new MatchFuzzy,
            new EvaluatePairs,
        ] as $rule) {
            $state = $rule($state, $input);
        }

        $escalations = ClassifyLeftovers::escalations($state, $input);
        $state = (new ClassifyLeftovers)($state, $input);

        return new EngineOutput($state->items, $escalations);
    }
}
