<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiSuggestion;
use Modules\AI\Support\Schemas;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Users\Models\User;

final class NarrativeService
{
    public function __construct(
        private readonly Redactor $redactor,
        private readonly SuggestionRecorder $recorder,
    ) {}

    public function summarise(User $user, ReconRun $run): AiSuggestion
    {
        $date = $run->business_date->toDateString();
        $summary = (array) $run->summary;
        $open = ReconException::query()->whereDate('business_date', $date)->whereIn('state', ReconException::openStateValues());
        $context = [
            'business_date' => $date,
            'run' => [
                'version' => $run->version,
                'provisional' => $run->provisional,
                'items' => $summary['items'] ?? null,
                'match_rate' => $summary['match_rate'] ?? null,
                'by_status' => $summary['by_status'] ?? [],
                'value_expected' => $summary['value_expected'] ?? null,
                'value_reconciled' => $summary['value_reconciled'] ?? null,
                'value_at_variance' => $summary['value_at_variance'] ?? null,
                'value_unmatched_payments' => $summary['value_unmatched_payments'] ?? null,
                'prior_day_cleared' => $summary['prior_day_cleared'] ?? null,
                'escalated_from_prior_day' => $summary['escalated_from_prior_day'] ?? null,
            ],
            'open_exceptions' => [
                'by_severity' => (clone $open)->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity')->all(),
                'by_category' => (clone $open)->selectRaw('category, count(*) as total, sum(amount_at_risk) as value')->groupBy('category')->get()
                    ->map(fn ($row): array => ['category' => $row->getAttribute('category'), 'count' => (int) $row->getAttribute('total'), 'value' => (string) $row->getAttribute('value')])->all(),
            ],
        ];

        return $this->recorder->record(
            $user,
            SuggestionKind::RunSummary,
            $this->redactor->redact($context),
            Schemas::runSummary(),
            Schemas::validRunSummary(...),
            ['run_id' => $run->id],
            SuggestionStatus::Informational,
        );
    }
}
