<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Models\ManualMatch;
use Modules\Reconciliation\Models\ReconResult;

function seedLatePaymentScenario(string $latePayer = '254700480480', string $lateAmount = '480.00'): array
{
    $store = app(MockSourceStore::class);
    $sale = fn (string $id, string $date, string $time, string $phone, string $amount) => ['transaction_id' => $id, 'business_date' => $date, 'timestamp' => "{$date} {$time}", 'agent_id' => 'AG-001', 'customer_phone' => $phone, 'region' => 'Coast', 'product_sku' => 'SOLAR-LAMP-S1', 'expected_amount' => $amount, 'currency' => 'USD', 'payment_reference' => $id];
    $posting = fn (string $id, string $date, string $amount) => ['journal_id' => 'JNL-'.$id, 'posting_date' => $date, 'transaction_id' => $id, 'account' => '4000-SALES-CASH', 'amount' => $amount, 'currency' => 'USD', 'status' => 'POSTED'];
    $payment = fn (string $id, string $at, string $phone, string $amount, ?string $ref) => ['payment_id' => $id, 'timestamp' => $at, 'channel' => 'MOBILE_MONEY', 'payer_phone' => $phone, 'amount' => $amount, 'currency' => 'USD', 'reference' => $ref];

    $store->replaceDay(SourceType::Sales, '2026-09-19', [$sale('TUP-S-480001', '2026-09-19', '10:00:00', '254700480480', '480.00'), $sale('TUP-S-480002', '2026-09-19', '11:00:00', '254700111222', '10.00')]);
    $store->replaceDay(SourceType::Postings, '2026-09-19', [$posting('TUP-S-480001', '2026-09-19', '480.00'), $posting('TUP-S-480002', '2026-09-19', '10.00')]);
    $store->replaceDay(SourceType::Payments, '2026-09-19', [$payment('SPAID19', '2026-09-19 11:30:00', '254700111222', '10.00', 'TUP-S-480002')]);

    $store->replaceDay(SourceType::Sales, '2026-09-22', [$sale('TUP-S-480003', '2026-09-22', '09:00:00', '254700333444', '20.00')]);
    $store->replaceDay(SourceType::Postings, '2026-09-22', [$posting('TUP-S-480003', '2026-09-22', '20.00')]);
    $store->replaceDay(SourceType::Payments, '2026-09-22', [
        $payment('SPAID22', '2026-09-22 09:30:00', '254700333444', '20.00', 'TUP-S-480003'),
        $payment('SLATE480', '2026-09-22 14:00:00', $latePayer, $lateAmount, null),
    ]);

    $saleResult = reconcile('2026-09-19', refresh: true)->results()->where('transaction_id', 'TUP-S-480001')->firstOrFail();
    $dayFour = reconcile('2026-09-22', refresh: true);

    return [$saleResult, $dayFour];
}

it('suggests an unmatched later payment from the same phone within tolerance as a possible match', function (): void {
    [$sale, $dayFour] = seedLatePaymentScenario();
    $unmatched = $dayFour->results()->where('status', 'UNMATCHED_PAYMENT')->firstOrFail();

    $this->actingAs(demoUser('analyst@demo'))
        ->getJson(route('results.possible-matches', $sale))
        ->assertOk()
        ->assertJsonPath('data.0.payment_result_id', $unmatched->id)
        ->assertJsonPath('data.0.payment_id', 'SLATE480')
        ->assertJsonPath('data.0.days_late', 3);
});

it('offers nothing when the phone differs or the amount is outside tolerance', function (string $phone, string $amount): void {
    [$sale] = seedLatePaymentScenario($phone, $amount);

    $this->actingAs(demoUser('analyst@demo'))->getJson(route('results.possible-matches', $sale))->assertOk()->assertJsonCount(0, 'data');
})->with([
    'other phone' => ['254700999999', '480.00'],
    'amount off by 0.51' => ['254700480480', '480.51'],
]);

it('confirms a possible match as an audited manual match that resolves both items and survives re-runs', function (): void {
    [$sale, $dayFour] = seedLatePaymentScenario();
    $unmatched = $dayFour->results()->where('status', 'UNMATCHED_PAYMENT')->firstOrFail();

    $this->actingAs(demoUser('analyst@demo'))
        ->postJson(route('results.manual-match', $sale), ['payment_result_id' => $unmatched->id, 'reason' => 'Customer confirmed by phone'])
        ->assertCreated();

    $match = ManualMatch::query()->firstOrFail();
    expect($match->confirmed_by)->toBe(demoUser('analyst@demo')->id)
        ->and($match->reason)->toBe('Customer confirmed by phone')
        ->and(ItemStateRecord::query()->find($sale->id)?->reason)->toStartWith('Paid late (D+3), manually matched by analyst@demo')
        ->and(ItemStateRecord::query()->find($unmatched->id)?->state->value)->toBe('resolved')
        ->and(AuditEvent::query()->where('action', 'match.manual_confirmed')->first()?->payload['reason'])->toBe('Customer confirmed by phone');

    $rerun = reconcile('2026-09-22');
    $row = ReconResult::query()->where('run_id', $rerun->id)->where('section', 'prior_day')->firstOrFail();
    expect($row->status->value)->toBe('MATCHED_PRIOR_DAY')
        ->and($row->rule_id)->toBe('MANUAL')
        ->and($row->tag)->toBe('Paid late (D+3), manually matched')
        ->and($rerun->results()->where('status', 'UNMATCHED_PAYMENT')->count())->toBe(0);

    $this->getJson(route('results.possible-matches', $sale))->assertJsonCount(0, 'data');
    $this->postJson(route('results.manual-match', $sale), ['payment_result_id' => $unmatched->id, 'reason' => 'Trying again'])->assertStatus(409);
});

it('requires a reason and the matches.confirm permission', function (): void {
    [$sale, $dayFour] = seedLatePaymentScenario();
    $unmatched = $dayFour->results()->where('status', 'UNMATCHED_PAYMENT')->firstOrFail();

    $this->actingAs(demoUser('analyst@demo'))->postJson(route('results.manual-match', $sale), ['payment_result_id' => $unmatched->id])->assertStatus(422);
    $this->actingAs(demoUser('auditor@demo'))->postJson(route('results.manual-match', $sale), ['payment_result_id' => $unmatched->id, 'reason' => 'Looks right'])->assertForbidden();
    expect(ManualMatch::query()->count())->toBe(0);
});
