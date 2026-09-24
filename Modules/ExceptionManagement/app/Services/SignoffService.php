<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\MatchReviewService;

final class SignoffService
{
    public function __construct(private readonly MatchReviewService $reviews) {}

    public function status(string $date): array
    {
        $run = ReconRun::query()->forDate($date)->latestCompleted()->first();
        $open = ReconException::query()->open()->whereDate('business_date', $date)->with('owner')->orderBy('id')->get();
        $blocking = $open->filter(fn (ReconException $e) => ! $e->soft && $e->severity->blocksSignOff())->values();
        $acknowledge = $open->reject(fn (ReconException $e) => ! $e->soft && $e->severity->blocksSignOff())->values();
        $pendingFuzzy = $this->reviews->pendingCount($date);
        $signoff = RunSignoff::query()->activeFor($date)->with('signer')->first();

        $blockers = [];
        if ($run === null) {
            $blockers[] = 'No completed reconciliation run for this date.';
        } elseif ($run->provisional) {
            $blockers[] = 'The latest run is provisional (the payment window was still open). Re-run after the window closes.';
        } elseif ($run->stale_at !== null) {
            $blockers[] = 'The latest run is stale because the previous date was re-run. Re-run this date first.';
        }
        if ($blocking->isNotEmpty()) {
            $blockers[] = "{$blocking->count()} open High or Critical exceptions must be resolved first.";
        }
        if ($pendingFuzzy > 0) {
            $blockers[] = "{$pendingFuzzy} fuzzy matches have not been reviewed.";
        }

        return [
            'date' => $date,
            'run' => $run,
            'signoff' => $signoff,
            'blockers' => $signoff === null ? $blockers : [],
            'blocking_exceptions' => $blocking,
            'to_acknowledge' => $acknowledge,
            'pending_fuzzy' => $pendingFuzzy,
            'can_sign' => $signoff === null && $blockers === [],
        ];
    }

    public function isLocked(string $date): bool
    {
        return RunSignoff::query()->activeFor($date)->exists();
    }
}
