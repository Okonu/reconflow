<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Actions;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Reconciliation\DTOs\PossibleMatch;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Models\ManualMatch;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Reconciliation\Services\ItemStateLedger;
use Modules\Reconciliation\Services\PossibleMatchFinder;
use Modules\Users\Models\User;

final class ConfirmManualMatch
{
    public function __construct(
        private readonly PossibleMatchFinder $finder,
        private readonly ItemStateLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, ReconResult $sale, int $paymentResultId, string $reason): ManualMatch
    {
        return DB::transaction(function () use ($user, $sale, $paymentResultId, $reason): ManualMatch {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('manual-match:'.$sale->transaction_id)]);
            $candidate = collect($this->finder->candidatesFor($sale))->first(fn (PossibleMatch $c) => $c->paymentResultId === $paymentResultId);
            if ($candidate === null) {
                throw DomainException::conflict('That payment is no longer a possible match for this sale.');
            }
            $tag = "Paid late (D+{$candidate->daysLate}), manually matched";

            $match = ManualMatch::query()->create([
                'transaction_id' => $sale->transaction_id,
                'sale_date' => $sale->business_date->toDateString(),
                'sale_record_id' => $sale->sale_record_id,
                'sale_result_id' => $sale->id,
                'payment_identity' => $candidate->identity,
                'payment_id' => $candidate->paymentId,
                'payment_date' => $candidate->paymentDate,
                'payment_result_id' => $candidate->paymentResultId,
                'confirmed_by' => $user->id,
                'reason' => $reason,
            ]);

            $this->ledger->resolveManually($sale->id, $sale->business_date->toDateString(), "{$tag} by {$user->email}: {$reason}", ReconStatus::MatchedPriorDay);
            $this->ledger->resolveManually($candidate->paymentResultId, $candidate->paymentDate, "Manually matched to {$sale->transaction_id} ({$sale->business_date->toDateString()}) by {$user->email}", ReconStatus::MatchedPriorDay);

            $this->audit->record(ReconAuditAction::ManualMatchConfirmed, $user, 'recon_result', $sale->id, [
                'manual_match_id' => $match->id,
                'transaction_id' => $sale->transaction_id,
                'sale_date' => $sale->business_date->toDateString(),
                'payment_id' => $candidate->paymentId,
                'payment_date' => $candidate->paymentDate,
                'payment_result_id' => $candidate->paymentResultId,
                'tag' => $tag,
                'reason' => $reason,
            ]);

            return $match;
        });
    }
}
