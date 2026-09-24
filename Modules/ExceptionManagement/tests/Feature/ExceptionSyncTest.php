<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Enums\SourceType;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Services\MatchReviewService;

it('opens one exception per variance or exception result, with category, severity, owner and SLA', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();

    expect(ReconException::query()->count())->toBe(22)
        ->and(ReconException::query()->where('soft', true)->count())->toBe(2)
        ->and(ReconException::query()->whereIn('status', ['MATCHED', 'MATCHED_TOLERANCE', 'MATCHED_SPLIT', 'MATCHED_FUZZY'])->count())->toBe(0);

    $under = exceptionFor('TUP-S-000041');
    expect($under->category)->toBe('Customer under/over-payment')
        ->and($under->severity->value)->toBe('medium')
        ->and((string) $under->amount_at_risk)->toBe('50.00')
        ->and($under->owner_id)->toBe(demoUser('analyst@demo')->id)
        ->and($under->due_at?->diffInHours($under->created_at, true))->toBe(72.0);

    expect(exceptionFor('TUP-S-000045')->severity->value)->toBe('medium')
        ->and(exceptionFor('TUP-S-000050')->due_at)->toBeNull()
        ->and(ReconException::query()->where('status', 'UNMATCHED_PAYMENT')->whereRaw("payment_ids::text like '%SL0LMI8X76%'")->first()?->severity->value)->toBe('high');
});

it('keeps exceptions, comments and owners when a date is re-run', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
    $exception = exceptionFor('TUP-S-000041');
    $this->post(route('exceptions.comment', $exception), ['comment' => 'Called the customer']);

    $rerun = reconcile();

    expect(ReconException::query()->count())->toBe(22)
        ->and($exception->fresh()->run_id)->toBe($rerun->id)
        ->and($exception->fresh()->events()->where('type', 'comment')->value('comment'))->toBe('Called the customer')
        ->and(AuditEvent::query()->where('action', 'exception.relinked')->exists())->toBeTrue();
});

it('splits a rejected fuzzy match into missing-payment and unmatched-payment exceptions and remembers it on re-run', function (): void {
    importAnswerKeyFiles('golden');
    $run = reconcile();
    $fuzzy = $run->results()->where('status', 'MATCHED_FUZZY')->where('transaction_id', 'TUP-S-000037')->firstOrFail();

    $this->post(route('matches.reject', $fuzzy), ['reason' => 'Different customer'])->assertSessionHas('success');

    expect(exceptionFor('TUP-S-000037')->status->value)->toBe('MISSING_PAYMENT')
        ->and(ReconException::query()->where('status', 'UNMATCHED_PAYMENT')->whereRaw("payment_ids::text like '%SY4SCOSS3E%'")->exists())->toBeTrue()
        ->and(ItemStateRecord::query()->find($fuzzy->id)?->effective_status->value)->toBe('MISSING_PAYMENT');

    $rerun = reconcile();
    expect($rerun->results()->where('transaction_id', 'TUP-S-000037')->value('status')?->value)->toBe('MISSING_PAYMENT')
        ->and(ReconException::query()->where('transaction_id', 'TUP-S-000037')->count())->toBe(1);
});

it('escalates a soft timing exception to a missing-payment exception when the next day brings no payment', function (): void {
    [, $store] = seedPendingSale();
    $store->replaceDay(SourceType::Payments, '2026-09-22', [
        ['payment_id' => 'STODAY0001', 'timestamp' => '2026-09-22 10:10:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990002'],
    ]);
    reconcile('2026-09-21', refresh: true);
    $soft = exceptionFor('TUP-S-990001', '2026-09-21');
    expect($soft->soft)->toBeTrue()->and($soft->due_at)->toBeNull();

    reconcile('2026-09-22', refresh: true);
    $escalated = $soft->fresh();

    expect($escalated->status->value)->toBe('MISSING_PAYMENT')
        ->and($escalated->soft)->toBeFalse()
        ->and($escalated->escalated_at)->not->toBeNull()
        ->and($escalated->due_at)->not->toBeNull()
        ->and($escalated->owner_id)->not->toBeNull();
});

it('resolves a timing exception when the sale is paid the next day', function (): void {
    [, $store] = seedPendingSale();
    $store->replaceDay(SourceType::Payments, '2026-09-22', [
        ['payment_id' => 'SNEXTDAY01', 'timestamp' => '2026-09-22 08:15:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990001'],
        ['payment_id' => 'STODAY0001', 'timestamp' => '2026-09-22 10:10:00', 'channel' => 'MOBILE_MONEY', 'payer_phone' => '254700990001', 'amount' => '28.00', 'currency' => 'USD', 'reference' => 'TUP-S-990002'],
    ]);
    reconcile('2026-09-21', refresh: true);
    reconcile('2026-09-22', refresh: true);

    $timing = exceptionFor('TUP-S-990001', '2026-09-21');
    expect($timing->state->value)->toBe('resolved')->and($timing->resolution)->toStartWith('Resolved on 2026-09-22 by payment SNEXTDAY01');
});

it('confirms fuzzy matches in bulk from the review list', function (): void {
    importAnswerKeyFiles('golden');
    $run = reconcile();
    $ids = $run->results()->where('status', 'MATCHED_FUZZY')->pluck('id')->all();

    $this->post(route('matches.confirm'), ['result_ids' => $ids])->assertSessionHas('success', 'Confirmed 4 fuzzy matches.');

    expect(app(MatchReviewService::class)->pendingCount('2026-09-22'))->toBe(0);
});
