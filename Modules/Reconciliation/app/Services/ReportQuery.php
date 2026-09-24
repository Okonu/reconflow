<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Reconciliation\DTOs\ReportFilters;
use Modules\Reconciliation\Models\ReconRun;

final class ReportQuery
{
    public function run(string $date): ?ReconRun
    {
        return ReconRun::query()->forDate($date)->latestCompleted()->orderByDesc('version')->first();
    }

    public function rows(ReconRun $run, ReportFilters $filters): Builder
    {
        return DB::table('recon_results as r')
            ->leftJoin('recon_item_states as st', 'st.result_id', '=', 'r.id')
            ->leftJoin('sales_records as s', 's.id', '=', 'r.sale_record_id')
            ->where('r.run_id', $run->id)
            ->when($filters->section !== 'all', fn (Builder $q) => $q->where('r.section', $filters->section))
            ->when($filters->rollUp, fn (Builder $q, string $v) => $q->where('r.roll_up', $v))
            ->when($filters->status, fn (Builder $q, string $v) => $q->whereRaw('coalesce(st.effective_status, r.status) = ?', [$v]))
            ->when($filters->search, fn (Builder $q, string $v) => $q->where(fn (Builder $w) => $w->where('r.transaction_id', 'ilike', "%{$v}%")->orWhereRaw('r.payment_ids::text ilike ?', ["%{$v}%"])))
            ->select([
                'r.id', 'r.business_date', 'r.section', 'r.transaction_id', 'r.payment_ids', 'r.expected_amount', 'r.actual_amount', 'r.posted_amount',
                'r.variance', 'r.status', 'r.roll_up', 'r.rule_id', 'r.tag', 'r.prior_date', 'r.sale_record_id', 'r.payment_record_ids',
                DB::raw('st.effective_status'), 's.customer_phone', 's.region', 's.agent_id',
                DB::raw('(select string_agg(p.payer_phone, \', \' order by p.paid_at) from payment_records p where p.id in (select (jsonb_array_elements_text(r.payment_record_ids))::bigint)) as payer_phones'),
            ])
            ->orderByRaw("case r.roll_up when 'Exception' then 1 when 'Variance' then 2 when 'Exception (soft)' then 3 when 'Match (flagged)' then 4 else 5 end")
            ->orderBy('r.id');
    }

    public function counts(ReconRun $run, string $section): array
    {
        return DB::table('recon_results')->where('run_id', $run->id)
            ->when($section !== 'all', fn (Builder $q) => $q->where('section', $section))
            ->selectRaw('roll_up, count(*) as total')->groupBy('roll_up')->pluck('total', 'roll_up')->map(fn ($v): int => (int) $v)->all();
    }
}
