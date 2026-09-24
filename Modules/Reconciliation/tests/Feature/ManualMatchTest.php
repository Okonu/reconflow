<?php

declare(strict_types=1);

use Modules\Audit\Models\AuditEvent;
use Modules\Reconciliation\Models\ItemStateRecord;
use Modules\Reconciliation\Models\ManualMatch;
use Modules\Reconciliation\Models\ReconResult;

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
