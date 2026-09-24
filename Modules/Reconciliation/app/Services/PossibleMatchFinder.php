<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reconciliation\DTOs\PossibleMatch;
use Modules\Reconciliation\Enums\ItemState;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Support\Cents;

final class PossibleMatchFinder
{
    public function __construct(private readonly RuleConfigService $configs) {}

    public function isEligible(ReconResult $result): bool
    {
        $superseded = ReconRun::query()->whereKey($result->run_id)->value('superseded_by_id');
        if ($result->section !== ResultSection::Current || $result->sale_record_id === null || $superseded !== null) {
            return false;
        }
        $state = ItemStateRecord::query()->find($result->id);
        $effective = $state === null ? $result->status : $state->effective_status;
        $current = $state === null ? ItemState::Open : $state->state;

        return $effective === ReconStatus::MissingPayment
            && in_array($current, [ItemState::Open, ItemState::Escalated], true)
            && ! DB::table('recon_manual_matches')->where('transaction_id', $result->transaction_id)->whereDate('sale_date', $result->business_date->toDateString())->exists();
    }

    public function candidatesFor(ReconResult $result): array
    {
        if (! $this->isEligible($result)) {
            return [];
        }
        $config = $this->configs->current();
        $sale = DB::table('sales_records')->where('id', $result->sale_record_id)->first(['customer_phone', 'expected_amount']);
        if ($sale === null) {
            return [];
        }
        $expected = Cents::fromDecimal((string) $sale->expected_amount);
        $saleDate = $result->business_date;
        $runIds = [];
        for ($offset = 0; $offset <= $config->latePaymentLookbackDays; $offset++) {
            $run = ReconRun::query()->forDate($saleDate->addDays($offset)->toDateString())->latestCompleted()->first();
            if ($run !== null) {
                $runIds[] = $run->id;
            }
        }

        $rows = DB::table('recon_results as r')
            ->leftJoin('recon_item_states as st', 'st.result_id', '=', 'r.id')
            ->whereIn('r.run_id', $runIds)
            ->where('r.status', ReconStatus::UnmatchedPayment->value)
            ->whereNull('st.state')
            ->orderBy('r.business_date')->orderBy('r.id')
            ->get(['r.id', 'r.business_date', 'r.payment_record_ids', 'r.payment_identities']);

        $candidates = [];
        foreach ($rows as $row) {
            $recordId = (int) (json_decode((string) $row->payment_record_ids, true)[0] ?? 0);
            $identity = (string) (json_decode((string) $row->payment_identities, true)[0] ?? '');
            $payment = DB::table('payment_records')->where('id', $recordId)->first();
            if ($payment === null || $payment->payer_phone !== $sale->customer_phone || DB::table('recon_manual_matches')->where('payment_identity', $identity)->exists()) {
                continue;
            }
            if (abs(Cents::fromDecimal((string) $payment->amount) - $expected) > $config->toleranceCents()) {
                continue;
            }
            $paymentDate = CarbonImmutable::parse((string) $row->business_date);
            $candidates[] = new PossibleMatch(
                paymentResultId: (int) $row->id,
                paymentRecordId: $recordId,
                paymentId: (string) $payment->payment_id,
                paymentDate: $paymentDate->toDateString(),
                paidAt: CarbonImmutable::parse((string) $payment->paid_at)->toIso8601String(),
                amount: (string) $payment->amount,
                reference: $payment->reference,
                identity: $identity,
                daysLate: (int) $saleDate->diffInDays($paymentDate),
            );
        }

        return $candidates;
    }
}
