<?php

declare(strict_types=1);

namespace Modules\Notifications\Services;

use App\Support\BusinessCalendar;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Models\Adjustment;
use Modules\ExceptionManagement\Enums\Severity;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Notifications\Enums\AlertLevel;
use Modules\Notifications\Notifications\Alert;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\MatchReviewService;

final class DailySummaryBuilder
{
    public function __construct(private readonly MatchReviewService $reviews) {}

    public function build(?string $date = null): Alert
    {
        $date ??= BusinessCalendar::latestClosedDate();
        $run = ReconRun::query()->forDate($date)->latestCompleted()->orderByDesc('version')->first();
        $open = ReconException::query()->open()->where('soft', false);
        $bySeverity = (clone $open)->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity')->all();
        $overdue = (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $approvals = Adjustment::query()->where('state', AdjustmentState::PendingApproval->value)->count();
        $failedPostings = Adjustment::query()->where('state', AdjustmentState::PostingFailed->value)->count();
        $fuzzy = $this->reviews->pendingCount($date);

        $body = $run === null
            ? "No completed reconciliation for {$date} yet."
            : sprintf('%s: %s%% matched across %d items (run v%d%s).', $date, $run->summary['match_rate'] ?? 'n/a', (int) ($run->summary['items'] ?? 0), $run->version, $run->provisional ? ', provisional' : '');

        $lines = [
            sprintf('Open exceptions: %d critical, %d high, %d medium, %d low', ...array_map(fn (Severity $s): int => (int) ($bySeverity[$s->value] ?? 0), [Severity::Critical, Severity::High, Severity::Medium, Severity::Low])),
            "Past SLA: {$overdue}",
            "Adjustments awaiting approval: {$approvals}",
            "Fuzzy matches awaiting review for {$date}: {$fuzzy}",
        ];
        if ($failedPostings > 0) {
            $lines[] = "ERP postings failed and waiting for retry: {$failedPostings}";
        }

        $level = ((int) ($bySeverity[Severity::Critical->value] ?? 0) > 0 || $run === null) ? AlertLevel::Warning : AlertLevel::Info;

        return new Alert('daily_summary', "Daily reconciliation summary for {$date}", $body, $run === null ? route('runs.index') : route('runs.show', $run), $level, $lines);
    }
}
