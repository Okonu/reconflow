<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Collection;
use Modules\AI\Enums\SuggestionKind;
use Modules\AI\Enums\SuggestionStatus;
use Modules\AI\Models\AiEvalRun;
use Modules\AI\Models\AiSuggestion;

final class OversightStats
{
    public function summary(int $days = 30): array
    {
        $since = now()->subDays($days);
        $triage = AiSuggestion::query()->where('kind', SuggestionKind::Triage->value)->where('created_at', '>=', $since);
        $byStatus = (clone $triage)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($v): int => (int) $v)->all();
        $accepted = $byStatus[SuggestionStatus::Accepted->value] ?? 0;
        $overridden = $byStatus[SuggestionStatus::Overridden->value] ?? 0;
        $decided = $accepted + $overridden;
        $all = AiSuggestion::query()->where('created_at', '>=', $since);

        return [
            'days' => $days,
            'triage' => [
                'total' => array_sum($byStatus),
                'pending' => $byStatus[SuggestionStatus::Pending->value] ?? 0,
                'accepted' => $accepted,
                'overridden' => $overridden,
                'failed' => $byStatus[SuggestionStatus::Failed->value] ?? 0,
                'acceptance_rate' => $decided === 0 ? null : round($accepted * 100 / $decided, 1),
            ],
            'summaries' => (clone $all)->where('kind', SuggestionKind::RunSummary->value)->count(),
            'avg_latency_ms' => (int) round((float) (clone $all)->whereNot('status', SuggestionStatus::Failed->value)->avg('latency_ms')),
            'input_tokens' => (int) (clone $all)->sum('input_tokens'),
            'output_tokens' => (int) (clone $all)->sum('output_tokens'),
            'fallback_served' => (clone $all)->where('served_by_fallback', true)->count(),
            'avg_confidence' => ($confidence = (clone $triage)->whereNotNull('output')->selectRaw("avg((output->>'confidence')::numeric) as c")->value('c')) === null ? null : round((float) $confidence * 100, 1),
            'failure_rate' => array_sum($byStatus) === 0 ? null : round(($byStatus[SuggestionStatus::Failed->value] ?? 0) * 100 / array_sum($byStatus), 1),
            'by_category' => (clone $triage)
                ->selectRaw("input->'exception'->>'category' as category, count(*) filter (where status = 'accepted') as accepted, count(*) filter (where status = 'overridden') as overridden")
                ->groupByRaw("input->'exception'->>'category'")->get()
                ->map(fn ($row): array => [
                    'category' => (string) $row->getAttribute('category'),
                    'accepted' => (int) $row->getAttribute('accepted'),
                    'overridden' => (int) $row->getAttribute('overridden'),
                ])->all(),
        ];
    }

    public function recentOverrides(int $limit = 20): Collection
    {
        return AiSuggestion::query()->with(['requester', 'decider'])->where('status', SuggestionStatus::Overridden->value)->latest('decided_at')->limit($limit)->get();
    }

    public function recent(int $limit = 25): Collection
    {
        return AiSuggestion::query()->with(['requester', 'decider'])->latest('id')->limit($limit)->get();
    }

    public function evals(int $limit = 10): array
    {
        return AiEvalRun::query()->latest('id')->limit($limit)->get()->map(fn (AiEvalRun $run): array => [
            'id' => $run->id,
            'eval_set' => $run->eval_set,
            'model' => $run->model,
            'prompt_version' => $run->prompt_version,
            'cases' => $run->cases,
            'action_correct' => $run->action_correct,
            'cause_correct' => $run->cause_correct,
            'errors' => $run->errors,
            'created_at' => $run->created_at?->toIso8601String(),
        ])->all();
    }
}
