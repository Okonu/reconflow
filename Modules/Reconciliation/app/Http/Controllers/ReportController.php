<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Services\AuditLogger;
use Modules\DataProtection\Support\PersonalData;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\RollUp;
use Modules\Reconciliation\Http\Requests\ReportRequest;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\ReportExporter;
use Modules\Reconciliation\Services\ReportQuery;
use stdClass;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportQuery $report): Response
    {
        $filters = $request->filters();
        $run = $report->run($filters->date);
        $user = $request->user();

        return Inertia::render('Reconciliation/Report/Index', [
            'filters' => $filters->toArray(),
            'run' => $run === null ? null : ['id' => $run->id, 'version' => $run->version, 'provisional' => $run->provisional, 'stale' => $run->stale_at !== null],
            'counts' => $run === null ? [] : $report->counts($run, $filters->section),
            'rows' => $run === null ? null : $report->rows($run, $filters)->paginate(50)->withQueryString()->through(fn (stdClass $row): array => [
                'id' => $row->id,
                'section' => $row->section,
                'transaction_id' => $row->transaction_id,
                'payment_ids' => (array) json_decode((string) $row->payment_ids, true),
                'expected_amount' => $row->expected_amount,
                'actual_amount' => $row->actual_amount,
                'posted_amount' => $row->posted_amount,
                'variance' => $row->variance,
                'status' => $row->effective_status ?? $row->status,
                'original_status' => $row->effective_status !== null && $row->effective_status !== $row->status ? $row->status : null,
                'roll_up' => $row->roll_up,
                'rule_id' => $row->rule_id,
                'tag' => $row->tag,
                'prior_date' => $row->prior_date,
                'customer_phone' => PersonalData::maskPhone($row->customer_phone),
                'region' => $row->region,
            ]),
            'options' => [
                'roll_ups' => array_column(RollUp::cases(), 'value'),
                'statuses' => array_map(fn (ReconStatus $s): array => ['value' => $s->value, 'label' => $s->label()], ReconStatus::cases()),
            ],
            'can' => [
                'export' => $user?->can('export', ReconRun::class) ?? false,
                'export_unmasked' => $user?->can('exportUnmasked', ReconRun::class) ?? false,
            ],
        ]);
    }

    public function export(ReportRequest $request, ReportQuery $report, ReportExporter $exporter, AuditLogger $audit): HttpResponse|RedirectResponse
    {
        return $this->download($request, $report, $exporter, $audit, true);
    }

    public function exportUnmasked(ReportRequest $request, ReportQuery $report, ReportExporter $exporter, AuditLogger $audit): HttpResponse|RedirectResponse
    {
        return $this->download($request, $report, $exporter, $audit, false);
    }

    private function download(ReportRequest $request, ReportQuery $report, ReportExporter $exporter, AuditLogger $audit, bool $masked): HttpResponse|RedirectResponse
    {
        $filters = $request->filters();
        $run = $report->run($filters->date);
        if ($run === null) {
            return back()->with('error', "No completed reconciliation for {$filters->date}.");
        }
        $rows = $report->rows($run, $filters);
        $format = $request->format();
        $audit->record(ReconAuditAction::ReportExported, $request->user(), 'recon_run', $run->id, [
            'business_date' => $filters->date,
            'format' => $format,
            'masked' => $masked,
            'filters' => $filters->toArray(),
            'rows' => (clone $rows)->count(),
            'reason' => $masked ? null : trim((string) $request->validated('reason')),
        ]);
        $filename = sprintf('reconciliation_%s_v%d%s.%s', $filters->date, $run->version, $masked ? '' : '_UNMASKED', $format);

        return $format === 'csv' ? $exporter->csv($rows, $filename, $masked) : $exporter->xlsx($rows, $filename, $masked);
    }
}
