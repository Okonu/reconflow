<?php

declare(strict_types=1);

namespace Modules\AI\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AI\Enums\LikelyCause;
use Modules\AI\Enums\RecommendedAction;
use Modules\AI\Models\AiSuggestion;

final class AiSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;
        assert($s instanceof AiSuggestion);
        $output = (array) $s->output;

        return [
            'id' => $s->id,
            'kind' => $s->kind->value,
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'exception_id' => $s->exception_id,
            'run_id' => $s->run_id,
            'model' => $s->model,
            'origin_label' => str_starts_with($s->model, 'stub') ? 'Offline rules stub (no AI key configured)' : 'AI-generated suggestion',
            'prompt_version' => $s->prompt_version,
            'served_by_fallback' => $s->served_by_fallback,
            'output' => $output,
            'likely_cause_label' => LikelyCause::tryFrom((string) ($output['likely_cause'] ?? ''))?->label(),
            'recommended_action_label' => RecommendedAction::tryFrom((string) ($output['recommended_action'] ?? ''))?->label(),
            'override_action_label' => RecommendedAction::tryFrom((string) $s->override_action)?->label(),
            'error' => $s->error,
            'latency_ms' => $s->latency_ms,
            'input_tokens' => $s->input_tokens,
            'output_tokens' => $s->output_tokens,
            'requested_by' => $s->requester?->getAttribute('name'),
            'decided_by' => $s->decider?->getAttribute('name'),
            'decision_reason' => $s->decision_reason,
            'decided_at' => $s->decided_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
            'can' => ['decide' => $request->user()?->can('decide', $s) ?? false],
        ];
    }
}
