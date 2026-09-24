<?php

declare(strict_types=1);

namespace Modules\Dashboard\Services;

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Models\Adjustment;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\ExceptionManagement\Models\RunSignoff;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\MatchReviewService;

final class DashboardData
{
    public function __construct(private readonly MatchReviewService $reviews) {}

    public function build(?string $date = null): array
    {
        $date ??= BusinessCalendar::latestClosedDate();
        $run = ReconRun::query()->forDate($date)->latestCompleted()->orderByDesc('version')->first();
        $latestAny = ReconRun::query()->forDate($date)->orderByDesc('version')->first();
        $summary = (array) ($run->summary ?? []);
        $open = ReconException::query()->whereIn('state', ReconException::openStateValues());
        $matched = (int) round(((float) ($summary['match_rate'] ?? 0)) / 100 * (int) ($summary['sale_items'] ?? 0));

        return [
            'date' => $date,
            'preparing' => (bool) config('ingestion.demo.auto_seed') && $run === null && DB::table('jobs')->exists(),
            'kpis' => [
                'match_rate' => $summary['match_rate'] ?? null,
                'items' => $summary['items'] ?? null,
                'value_expected' => $summary['value_expected'] ?? null,
                'value_reconciled' => $summary['value_reconciled'] ?? null,
                'value_at_variance' => $summary['value_at_variance'] ?? null,
                'value_unmatched_payments' => $summary['value_unmatched_payments'] ?? null,
                'open_exceptions' => (clone $open)->where('soft', false)->count(),
                'timing_items' => (clone $open)->where('soft', true)->count(),
                'overdue_exceptions' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'critical_open' => (clone $open)->where('severity', 'critical')->count(),
                'pending_approvals' => Adjustment::query()->where('state', AdjustmentState::PendingApproval->value)->count(),
                'posting_failed' => Adjustment::query()->where('state', AdjustmentState::PostingFailed->value)->count(),
                'pending_fuzzy' => $this->reviews->pendingCount($date),
                'minutes_saved' => $run === null ? null : (int) round($matched * (float) config('dashboard.manual_seconds_per_item') / 60),
                'prior_day_cleared' => $summary['prior_day_cleared'] ?? null,
            ],
            'latest_run' => $latestAny === null ? null : [
                'id' => $latestAny->id,
                'version' => $latestAny->version,
                'status' => $latestAny->status->value,
                'provisional' => $latestAny->provisional,
                'stale' => $latestAny->stale_at !== null,
                'duration_ms' => $latestAny->duration_ms,
                'finished_at' => $latestAny->finished_at?->toIso8601String(),
                'blocked_reason' => $latestAny->blocked_reason,
                'batches' => collect((array) $latestAny->batches)->map(fn ($b, $source): array => [
                    'source' => (string) $source,
                    'rows' => is_array($b) ? (int) ($b['rows'] ?? 0) : null,
                    'quarantined' => is_array($b) ? (int) ($b['quarantined'] ?? 0) : null,
                    'manual' => is_array($b) && (bool) ($b['manual'] ?? false),
                ])->values()->all(),
                'signed_off' => RunSignoff::query()->whereDate('business_date', $date)->whereNull('reopened_at')->exists(),
            ],
            'trend' => $this->trend($date),
            'by_category' => (clone $open)->where('soft', false)->selectRaw('category, count(*) as total, sum(amount_at_risk) as value')->groupBy('category')->orderByDesc('total')->get()
                ->map(fn ($row): array => ['category' => (string) $row->getAttribute('category'), 'count' => (int) $row->getAttribute('total'), 'value' => (string) $row->getAttribute('value')])->all(),
            'ageing' => $this->ageing(),
            'manual_seconds_per_item' => (float) config('dashboard.manual_seconds_per_item'),
        ];
    }

    private function trend(string $date): array
    {
        $end = CarbonImmutable::parse($date);
        $start = $end->subDays((int) config('dashboard.trend_days') - 1);
        $runs = ReconRun::query()->latestCompleted()->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('business_date')->orderByDesc('version')->get()->unique(fn (ReconRun $r): string => $r->business_date->toDateString())
            ->keyBy(fn (ReconRun $r): string => $r->business_date->toDateString());
        $points = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $run = $runs->get($d->toDateString());
            $summary = (array) ($run->summary ?? []);
            $points[] = [
                'date' => $d->toDateString(),
                'match_rate' => isset($summary['match_rate']) ? (float) $summary['match_rate'] : null,
                'exceptions' => isset($summary['exceptions']) ? (int) $summary['exceptions'] : null,
                'value_at_variance' => isset($summary['value_at_variance']) ? (float) $summary['value_at_variance'] : null,
            ];
        }

        return $points;
    }

    private function ageing(): array
    {
        $row = DB::table('exceptions')->whereIn('state', ReconException::openStateValues())->where('soft', false)->selectRaw("
            count(*) filter (where created_at >= now() - interval '1 day') as d0,
            count(*) filter (where created_at < now() - interval '1 day' and created_at >= now() - interval '3 days') as d1,
            count(*) filter (where created_at < now() - interval '3 days' and created_at >= now() - interval '7 days') as d3,
            count(*) filter (where created_at < now() - interval '7 days') as d7
        ")->first();

        return [
            ['bucket' => '< 1 day', 'count' => (int) ($row->d0 ?? 0)],
            ['bucket' => '1–3 days', 'count' => (int) ($row->d1 ?? 0)],
            ['bucket' => '3–7 days', 'count' => (int) ($row->d3 ?? 0)],
            ['bucket' => '> 7 days', 'count' => (int) ($row->d7 ?? 0)],
        ];
    }
}
