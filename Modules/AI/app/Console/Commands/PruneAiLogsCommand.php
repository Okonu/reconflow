<?php

declare(strict_types=1);

namespace Modules\AI\Console\Commands;

use Illuminate\Console\Command;
use Modules\AI\Enums\AiAuditAction;
use Modules\AI\Models\AiEvalRun;
use Modules\AI\Models\AiSuggestion;
use Modules\Audit\Services\AuditLogger;

final class PruneAiLogsCommand extends Command
{
    protected $signature = 'reconflow:ai-prune';

    protected $description = 'Delete AI suggestion logs older than the retention period';

    public function handle(AuditLogger $audit): int
    {
        $months = (int) config('ai.log_retention_months');
        $cutoff = now()->subMonths($months);
        $suggestions = AiSuggestion::query()->where('created_at', '<', $cutoff)->delete();
        $evals = AiEvalRun::query()->where('created_at', '<', $cutoff)->delete();
        if ($suggestions + $evals > 0) {
            $audit->record(AiAuditAction::LogsPruned, null, 'ai_suggestion', null, ['older_than' => $cutoff->toIso8601String(), 'suggestions' => $suggestions, 'eval_runs' => $evals]);
        }
        $this->info("Pruned {$suggestions} suggestions and {$evals} eval runs older than {$months} months.");

        return self::SUCCESS;
    }
}
