<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Reconciliation\DTOs\RuleConfig;
use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Engine\SaleInput;
use Modules\Reconciliation\Enums\ItemState;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Support\Cents;

final class ItemStateLedger
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function openPriorItems(string $businessDate, RuleConfig $config): array
    {
        $date = CarbonImmutable::parse($businessDate);
        $horizon = max($config->timingCarryDays, $config->latePaymentLookbackDays);
        $runIds = [];
        for ($offset = 1; $offset <= $horizon; $offset++) {
            $run = ReconRun::query()->forDate($date->subDays($offset)->toDateString())->latestCompleted()->first();
            if ($run !== null) {
                $runIds[$run->id] = $offset;
            }
        }
        if ($runIds === []) {
            return [];
        }

        $rows = DB::table('recon_results as r')
            ->join('sales_records as s', 's.id', '=', 'r.sale_record_id')
            ->leftJoin('recon_item_states as st', 'st.result_id', '=', 'r.id')
            ->whereIn('r.run_id', array_keys($runIds))
            ->where('r.section', 'current')
            ->whereIn('r.status', [ReconStatus::PendingTiming->value, ReconStatus::MissingPayment->value])
            ->where(fn ($q) => $q->whereNull('st.state')->orWhereIn('st.state', [ItemState::Open->value, ItemState::Escalated->value]))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('recon_manual_matches as mm')->whereColumn('mm.transaction_id', 'r.transaction_id')->whereColumn('mm.sale_date', 'r.business_date'))
            ->orderBy('r.id')
            ->get(['r.id', 'r.run_id', 'r.business_date', 'r.status', 'r.transaction_id', 'r.expected_amount', 'st.state', 's.id as sale_id', 's.sold_at', 's.customer_phone', 's.payment_reference']);

        $items = [];
        foreach ($rows as $row) {
            $offset = $runIds[$row->run_id];
            $carried = $row->status === ReconStatus::PendingTiming->value && $row->state !== ItemState::Escalated->value;
            if ($carried ? $offset > $config->timingCarryDays : $offset > $config->latePaymentLookbackDays) {
                continue;
            }
            $items[] = new SaleInput(
                key: 'prior:'.$row->id,
                transactionId: (string) $row->transaction_id,
                soldAt: (int) strtotime((string) $row->sold_at),
                phone: (string) $row->customer_phone,
                expectedCents: Cents::fromDecimal((string) $row->expected_amount),
                reference: $row->payment_reference,
                origin: $carried ? SaleInput::CARRIED : SaleInput::LOOKBACK,
                recordId: (int) $row->sale_id,
                priorResultId: (int) $row->id,
                priorDate: CarbonImmutable::parse((string) $row->business_date)->toDateString(),
            );
        }

        return $items;
    }

    public function resolveManually(int $resultId, string $date, string $reason, ReconStatus $effective): void
    {
        ItemStateRecord::query()->updateOrCreate(['result_id' => $resultId], [
            'business_date' => $date,
            'state' => ItemState::Resolved,
            'effective_status' => $effective,
            'resolved_by_run_id' => null,
            'resolved_by_result_id' => null,
            'reason' => $reason,
        ]);
    }

    public function releaseDecisionsBy(array $runIds, ReconRun $newRun): int
    {
        $records = ItemStateRecord::query()->whereIn('resolved_by_run_id', $runIds)->get();
        foreach ($records as $record) {
            $this->audit->record(ReconAuditAction::ItemReopened, AuditLogger::SYSTEM_ACTOR, 'recon_result', $record->result_id, [
                'previous_state' => $record->state->value,
                'reason' => "Recomputed by run {$newRun->id} (version {$newRun->version})",
            ]);
        }
        ItemStateRecord::query()->whereIn('resolved_by_run_id', $runIds)->delete();

        return $records->count();
    }

    public function record(ReconRun $run, array $priorResults, array $escalations): void
    {
        foreach ($priorResults as [$item, $resultId]) {
            assert($item instanceof ResultItem);
            $resolved = in_array($item->status, [ReconStatus::MatchedPriorDay, ReconStatus::MatchedFuzzy], true);
            $payments = implode(', ', $item->paymentIds());
            $reason = match (true) {
                ($item->flags['manual_match'] ?? false) === true => "{$item->tag} (confirmed manual match, payment {$payments})",
                $resolved => "Resolved on {$run->business_date->toDateString()} by payment {$payments}",
                default => "Payment {$payments} received on {$run->business_date->toDateString()}; now {$item->status->value}",
            };
            $this->write($item->sale?->priorResultId, $item->sale?->priorDate, $resolved ? ItemState::Resolved : ItemState::Reclassified, $item->status, $run->id, $resultId, $reason);
            $this->audit->record($resolved ? ReconAuditAction::ItemResolved : ReconAuditAction::ItemReclassified, AuditLogger::SYSTEM_ACTOR, 'recon_result', $item->sale?->priorResultId, [
                'resolved_by_run_id' => $run->id,
                'resolved_by_result_id' => $resultId,
                'status' => $item->status->value,
                'tag' => $item->tag,
                'reason' => $reason,
            ]);
        }

        foreach ($escalations as $priorResultId) {
            $date = DB::table('recon_results')->where('id', $priorResultId)->value('business_date');
            $reason = 'no payment by close of D+1 window';
            $this->write($priorResultId, (string) $date, ItemState::Escalated, ReconStatus::MissingPayment, $run->id, null, $reason);
            $this->audit->record(ReconAuditAction::ItemEscalated, AuditLogger::SYSTEM_ACTOR, 'recon_result', $priorResultId, [
                'from' => ReconStatus::PendingTiming->value,
                'to' => ReconStatus::MissingPayment->value,
                'escalated_by_run_id' => $run->id,
                'reason' => $reason,
            ]);
        }
    }

    private function write(?int $resultId, ?string $date, ItemState $state, ReconStatus $effective, int $runId, ?int $byResultId, string $reason): void
    {
        if ($resultId === null) {
            return;
        }
        ItemStateRecord::query()->updateOrCreate(['result_id' => $resultId], [
            'business_date' => $date,
            'state' => $state,
            'effective_status' => $effective,
            'resolved_by_run_id' => $runId,
            'resolved_by_result_id' => $byResultId,
            'reason' => $reason,
        ]);
    }
}
