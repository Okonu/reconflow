<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Adjustments\Actions\PostAdjustment;
use Modules\Adjustments\Models\Adjustment;
use Modules\Adjustments\Models\ErpPostingOut;
use Modules\Audit\Services\ChainVerifier;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockErp;
use Modules\Ingestion\Services\MockSourceStore;

it('runs the full exception, maker-checker, ERP posting and sign-off flow', function (): void {
    importAnswerKeyFiles('golden');
    $run = reconcile();
    $analyst = demoUser('analyst@demo');
    $manager = demoUser('manager@demo');

    $exception = exceptionFor('TUP-S-000041');
    $this->actingAs($analyst)->get(route('exceptions.show', $exception))->assertOk();
    $this->actingAs($analyst)->post(route('exceptions.review', $exception))->assertSessionHas('success');
    $this->actingAs($analyst)->post(route('adjustments.store', $exception), ['type' => 'write_off', 'amount' => '50.00', 'reason' => 'Customer short-paid; agreed write-off'])->assertSessionHas('success');
    $adjustment = Adjustment::query()->firstOrFail();
    expect($exception->fresh()->state->value)->toBe('pending_approval')
        ->and($adjustment->journal['lines'][0]['debit'])->toBe('50.00');

    $this->actingAs($analyst)->postJson(route('adjustments.approve', $adjustment))
        ->assertForbidden()
        ->assertJsonPath('error.message', 'You proposed this adjustment, so someone else must approve or reject it (segregation of duties).');

    $this->actingAs($manager)->post(route('adjustments.approve', $adjustment), ['comment' => 'OK'])->assertSessionHas('success');
    $posted = $adjustment->fresh();
    expect($posted->state->value)->toBe('posted')
        ->and($posted->erp_journal_id)->toStartWith('ADJ-2026-')
        ->and($exception->fresh()->state->value)->toBe('resolved')
        ->and(ErpPostingOut::query()->where('idempotency_key', $posted->idempotency_key)->count())->toBe(1);

    app(PostAdjustment::class)->handle($posted);
    $replay = app(MockErp::class)->post(['idempotency_key' => $posted->idempotency_key, ...$posted->journal]);
    expect(ErpPostingOut::query()->count())->toBe(1)
        ->and(DB::table('mock_erp_journals')->count())->toBe(1)
        ->and($replay['journal_id'])->toBe($posted->erp_journal_id)
        ->and($replay['replayed'])->toBeTrue();

    $blocked = $this->actingAs($manager)->get(route('signoff.show', '2026-09-22'));
    $blocked->assertInertia(fn ($page) => $page->where('can.sign', false)->where('pending_fuzzy', 4));
    $this->actingAs($analyst)->post(route('matches.confirm'), ['result_ids' => $run->results()->where('status', 'MATCHED_FUZZY')->pluck('id')->all()]);
    foreach (ReconException::query()->open()->whereIn('severity', ['high', 'critical'])->get() as $high) {
        $this->actingAs($analyst)->post(route('exceptions.resolve', $high), ['reason' => 'Unidentified receipt investigated; no action needed']);
    }

    $this->actingAs($manager)->post(route('signoff.store', '2026-09-22'), [])->assertSessionHas('error');
    $this->actingAs($manager)->post(route('signoff.store', '2026-09-22'), ['comment' => 'Remaining items carried forward to tomorrow'])->assertSessionHas('success');

    expect(fn () => reconcile())->toThrow(DomainException::class, 'signed off');
    $staging = stageFile(samplePath('golden/sales_2026-09-22.xlsx'), 'sales');
    $this->actingAs($analyst)->post(route('ingestion.uploads.confirm', $staging), ['mode' => 'replace'])->assertSessionHas('error');

    $this->actingAs($analyst)->post(route('signoff.reopen', '2026-09-22'), ['reason' => 'Late correction'])->assertForbidden();
    $this->actingAs($manager)->post(route('signoff.reopen', '2026-09-22'), ['reason' => 'Late ERP correction arrived'])->assertSessionHas('success');
    expect(reconcile()->status->value)->toBe('completed')
        ->and(app(ChainVerifier::class)->verify()->ok)->toBeTrue();
});

it('needs a Finance Manager above the approval threshold', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
    $exception = exceptionFor('TUP-S-000052');
    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', $exception), ['type' => 'post_missing', 'amount' => '1500.00', 'reason' => 'Large manual posting']);
    $adjustment = Adjustment::query()->firstOrFail();
    $approverWithoutHighValue = userWithPermissions(['adjustments.approve', 'adjustments.view']);

    expect($adjustment->high_value)->toBeTrue();
    $this->actingAs($approverWithoutHighValue)->postJson(route('adjustments.approve', $adjustment))
        ->assertForbidden()
        ->assertJsonPath('error.message', 'Adjustments above $1,000.00 need a Finance Manager (adjustments.approve_high_value).');
    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.approve', $adjustment))->assertSessionHas('success');
    expect($adjustment->fresh()->state->value)->toBe('posted');
});

it('marks a failed ERP posting and posts it on retry with the same idempotency key', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
    app(MockErp::class)->setFailureSimulation(true);
    $exception = exceptionFor('TUP-S-000053');
    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', $exception), ['type' => 'post_missing', 'amount' => '248.00', 'reason' => 'Post the missing sale']);
    $adjustment = Adjustment::query()->firstOrFail();

    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.approve', $adjustment));
    expect($adjustment->fresh()->state->value)->toBe('posting_failed')->and($exception->fresh()->state->value)->toBe('posting_failed');

    app(MockErp::class)->setFailureSimulation(false);
    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.retry', $adjustment))->assertSessionHas('success');
    expect($adjustment->fresh()->state->value)->toBe('posted')
        ->and(ErpPostingOut::query()->where('idempotency_key', $adjustment->idempotency_key)->pluck('succeeded')->all())->toBe([false, true])
        ->and($exception->fresh()->state->value)->toBe('resolved');
});

it('rejects an adjustment back into review with a comment', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();
    $exception = exceptionFor('TUP-S-000042');
    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', $exception), ['type' => 'write_off', 'amount' => '50.00', 'reason' => 'Write off']);
    $adjustment = Adjustment::query()->firstOrFail();

    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.reject', $adjustment), [])->assertSessionHasErrors('comment');
    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.reject', $adjustment), ['comment' => 'Chase the customer first'])->assertSessionHas('success');

    expect($adjustment->fresh()->state->value)->toBe('rejected')->and($exception->fresh()->state->value)->toBe('in_review');
});

it('refuses adjustment types that do not fit the exception', function (): void {
    importAnswerKeyFiles('golden');
    reconcile();

    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', exceptionFor('TUP-S-000041')), ['type' => 'post_missing', 'amount' => '50.00', 'reason' => 'Wrong type'])->assertSessionHas('error');
    expect(Adjustment::query()->count())->toBe(0);
});

it('reflects a posted missing-posting correction in the next pull and re-run', function (): void {
    [$saleResult] = seedLatePaymentScenario();
    $store = app(MockSourceStore::class);
    DB::table('mock_source_rows')->where('source', 'postings')->whereDate('record_date', '2026-09-22')->delete();
    $run = reconcile('2026-09-22', refresh: true);
    $exception = exceptionFor('TUP-S-480003');
    expect($exception->status->value)->toBe('MISSING_POSTING');

    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', $exception), ['type' => 'post_missing', 'amount' => '20.00', 'reason' => 'ERP interface dropped the line']);
    $this->actingAs(demoUser('manager@demo'))->post(route('adjustments.approve', Adjustment::query()->firstOrFail()));

    $rerun = reconcile('2026-09-22', refresh: true);
    expect($rerun->results()->where('transaction_id', 'TUP-S-480003')->value('status')?->value)->toBe('MATCHED')
        ->and($run->id)->not->toBe($rerun->id);
});

it('flags an exception with an adjustment in flight when a re-run removes it', function (): void {
    [$saleResult] = seedLatePaymentScenario();
    DB::table('mock_source_rows')->where('source', 'postings')->whereDate('record_date', '2026-09-22')->delete();
    reconcile('2026-09-22', refresh: true);
    $exception = exceptionFor('TUP-S-480003');
    $this->actingAs(demoUser('analyst@demo'))->post(route('adjustments.store', $exception), ['type' => 'post_missing', 'amount' => '20.00', 'reason' => 'Pending']);

    app(MockSourceStore::class)->replaceDay(SourceType::Postings, '2026-09-22', [
        ['journal_id' => 'JNL-LATE', 'posting_date' => '2026-09-22', 'transaction_id' => 'TUP-S-480003', 'account' => '4000-SALES-CASH', 'amount' => '20.00', 'currency' => 'USD', 'status' => 'POSTED'],
    ]);
    reconcile('2026-09-22', refresh: true);

    expect($exception->fresh()->needs_review)->toBeTrue()->and($exception->fresh()->state->value)->toBe('pending_approval');
});
