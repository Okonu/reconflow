<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use App\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Reconciliation\Enums\ItemState;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Events\FuzzyMatchRejected;
use Modules\Reconciliation\Models\MatchReview;
use Modules\Reconciliation\Models\ReconResult;
use Modules\Users\Models\User;

final class MatchReviewService
{
    public function __construct(
        private readonly ItemStateLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function pending(?string $date = null): Builder
    {
        return ReconResult::query()
            ->where('status', ReconStatus::MatchedFuzzy->value)
            ->whereHas('run', fn (Builder $q) => $q->where('status', RunStatus::Completed->value)->whereNull('superseded_by_id'))
            ->when($date, fn (Builder $q, string $d) => $q->whereDate('business_date', $d))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('recon_match_reviews as mr')
                ->whereColumn('mr.business_date', 'recon_results.business_date')
                ->whereColumn('mr.transaction_id', 'recon_results.transaction_id')
                ->whereRaw('mr.payment_identity = recon_results.payment_identities->>0'))
            ->orderByDesc('business_date')->orderBy('id');
    }

    public function pendingCount(string $date): int
    {
        return $this->pending($date)->count();
    }

    public function confirm(User $user, array $resultIds, string $reason = ''): int
    {
        return DB::transaction(function () use ($user, $resultIds, $reason): int {
            $results = $this->pending()->whereKey($resultIds)->get();
            foreach ($results as $result) {
                if (! $result instanceof ReconResult) {
                    continue;
                }
                $this->review($user, $result, 'confirmed', $reason);
                $this->audit->record(ReconAuditAction::FuzzyMatchConfirmed, $user, 'recon_result', $result->id, [
                    'business_date' => $result->business_date->toDateString(),
                    'transaction_id' => $result->transaction_id,
                    'payment_ids' => $result->payment_ids,
                    'confidence' => $result->match_confidence,
                    'reason' => $reason,
                ]);
            }

            return $results->count();
        });
    }

    public function reject(User $user, ReconResult $result, string $reason): void
    {
        DB::transaction(function () use ($user, $result, $reason): void {
            if (! $this->pending()->whereKey($result->id)->exists()) {
                throw DomainException::conflict('This fuzzy match has already been reviewed.');
            }
            $this->review($user, $result, 'rejected', $reason);
            $note = "Fuzzy match rejected by {$user->email}: {$reason}";
            if ($result->section === ResultSection::PriorDay && $result->prior_result_id !== null) {
                $this->ledger->resolveManually((int) $result->prior_result_id, (string) $result->prior_date?->toDateString(), $note, ReconStatus::MissingPayment, ItemState::Escalated);
            } else {
                $this->ledger->resolveManually($result->id, $result->business_date->toDateString(), $note, ReconStatus::MissingPayment, ItemState::Reclassified);
            }
            $this->audit->record(ReconAuditAction::FuzzyMatchRejected, $user, 'recon_result', $result->id, [
                'business_date' => $result->business_date->toDateString(),
                'transaction_id' => $result->transaction_id,
                'payment_ids' => $result->payment_ids,
                'reason' => $reason,
            ]);
            FuzzyMatchRejected::dispatch($result->id, $reason);
        });
    }

    private function review(User $user, ReconResult $result, string $decision, string $reason): void
    {
        MatchReview::query()->create([
            'business_date' => $result->business_date->toDateString(),
            'transaction_id' => (string) $result->transaction_id,
            'payment_identity' => (string) ($result->payment_identities[0] ?? ''),
            'decision' => $decision,
            'result_id' => $result->id,
            'decided_by' => $user->id,
            'reason' => $reason,
        ]);
    }
}
