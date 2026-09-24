<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reconciliation\Models\ReconRun;

final class ReconRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $run = $this->resource;
        assert($run instanceof ReconRun);

        return [
            'id' => $run->id,
            'business_date' => $run->business_date->toDateString(),
            'version' => $run->version,
            'status' => $run->status->value,
            'trigger' => $run->trigger->value,
            'triggered_by' => $run->triggeredBy?->getAttribute('name'),
            'provisional' => $run->provisional,
            'stale' => $run->stale_at !== null,
            'superseded' => $run->superseded_by_id !== null,
            'rule_config' => $run->rule_config,
            'batches' => $run->batches,
            'summary' => $run->summary,
            'blocked_reason' => $run->blocked_reason,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_ms' => $run->duration_ms,
        ];
    }
}
