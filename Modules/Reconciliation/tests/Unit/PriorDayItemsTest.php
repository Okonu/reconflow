<?php

declare(strict_types=1);

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\ReconciliationEngine;
use Modules\Reconciliation\Engine\SaleInput;

function priorItem(string $id, string $amount, string $origin, string $date, int $resultId): SaleInput
{
    return engineSale($id, $amount, $date.' 22:30:00', extra: ['key' => 'prior:'.$resultId, 'origin' => $origin, 'priorResultId' => $resultId, 'priorDate' => $date]);
}

it('clears a carried pending-timing sale paid the next day in the prior-day section', function (): void {
    $r = runEngine([], [enginePayment('P1', '28.00', 'T50', '2026-09-23 08:10:00')], enginePosting('T50', '28.00'), [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)], date: '2026-09-23');

    expect($r['items'])->toHaveCount(1)
        ->and($r['items'][0])->toMatchArray(['section' => 'prior_day', 'status' => 'MATCHED_PRIOR_DAY', 'tag' => 'Paid next day'])
        ->and($r['escalations'])->toBe([]);
});

it('escalates a carried sale still unpaid after its one carry day', function (): void {
    $r = runEngine([], [], [], [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)], date: '2026-09-23');

    expect($r['items'])->toBe([])->and($r['escalations'])->toBe([11]);
});

it('matches a late payment to an open missing-payment item from earlier in the week', function (): void {
    $r = runEngine([], [enginePayment('P9', '480.00', 'T48', '2026-09-25 09:00:00')], enginePosting('T48', '480.00'), [priorItem('T48', '480.00', SaleInput::LOOKBACK, '2026-09-22', 12)], date: '2026-09-25');

    expect($r['items'][0])->toMatchArray(['section' => 'prior_day', 'status' => 'MATCHED_PRIOR_DAY', 'tag' => 'Paid late (D+3)']);
});

it('applies the amount and posting checks to prior-day matches', function (): void {
    $variance = runEngine([], [enginePayment('P9', '100.00', 'T48', '2026-09-25 09:00:00')], enginePosting('T48', '480.00'), [priorItem('T48', '480.00', SaleInput::LOOKBACK, '2026-09-22', 12)], date: '2026-09-25');
    $posting = runEngine([], [enginePayment('P9', '480.00', 'T48', '2026-09-25 09:00:00')], [], [priorItem('T48', '480.00', SaleInput::LOOKBACK, '2026-09-22', 12)], date: '2026-09-25');

    expect($variance['items'][0])->toMatchArray(['section' => 'prior_day', 'status' => 'VARIANCE'])
        ->and($posting['items'][0])->toMatchArray(['section' => 'prior_day', 'status' => 'MISSING_POSTING']);
});

it('matches current-day sales before prior-day items and never lets fuzzy steal a referenced late payment', function (): void {
    $r = runEngine(
        [engineSale('T100', '28.00', '2026-09-23 09:00:00')],
        [enginePayment('P1', '28.00', 'T50', '2026-09-23 09:30:00')],
        enginePosting('T50', '28.00') + enginePosting('T100', '28.00'),
        [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)],
        date: '2026-09-23',
    );

    expect(statusOf($r, 'T50'))->toBe('MATCHED_PRIOR_DAY')->and(statusOf($r, 'T100'))->toBe('MISSING_PAYMENT');
});

it('fuzzy-matches a carried pending sale to a next-day payment and flags it for confirmation', function (): void {
    $r = runEngine([], [enginePayment('P1', '28.00', null, '2026-09-23 09:00:00')], enginePosting('T50', '28.00'), [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)], date: '2026-09-23');

    expect($r['items'][0])->toMatchArray(['section' => 'prior_day', 'status' => 'MATCHED_FUZZY', 'rule' => 'R3', 'tag' => 'Paid next day'])
        ->and($r['items'][0]['flags']['needs_confirmation'])->toBeTrue()
        ->and($r['escalations'])->toBe([]);
});

it('measures the carried fuzzy window from the sale timestamp, inclusive of 24 hours', function (string $paidAt, bool $matched): void {
    $r = runEngine([], [enginePayment('P1', '28.00', null, $paidAt)], [], [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)], date: '2026-09-23');

    expect($r['escalations'] === [])->toBe($matched);
})->with([
    'exactly 24h after the 22:30 sale' => ['2026-09-23 22:30:00', true],
    '24h and 1s' => ['2026-09-23 22:30:01', false],
]);

it('treats a payment that fits both a carried sale and a current sale as a tie and matches neither', function (): void {
    $r = runEngine(
        [engineSale('T100', '28.00', '2026-09-23 09:00:00')],
        [enginePayment('P1', '28.00', null, '2026-09-23 09:30:00')],
        [],
        [priorItem('T50', '28.00', SaleInput::CARRIED, '2026-09-22', 11)],
        date: '2026-09-23',
    );

    expect(statusOf($r, 'T100'))->toBe('MISSING_PAYMENT')
        ->and(collect($r['items'])->firstWhere('txn', 'T100')['rule'])->toBe('R3 tie → R7')
        ->and(collect($r['items'])->firstWhere('status', 'UNMATCHED_PAYMENT')['rule'])->toBe('R3 tie → R7')
        ->and($r['escalations'])->toBe([11]);
});

it('never fuzzy-matches an escalated or missing-payment item from the lookback automatically', function (): void {
    $r = runEngine([], [enginePayment('P9', '480.00', null, '2026-09-25 09:00:00')], [], [priorItem('T48', '480.00', SaleInput::LOOKBACK, '2026-09-22', 12)], date: '2026-09-25');

    expect(collect($r['items'])->pluck('status')->all())->toBe(['UNMATCHED_PAYMENT']);
});

it('flags current-day fuzzy matches as needing confirmation', function (): void {
    $r = runEngine([engineSale('T1', '37.80')], [enginePayment('P1', '37.80', null, '2026-09-22 11:00:00')], enginePosting('T1', '37.80'));

    expect($r['items'][0]['flags']['needs_confirmation'])->toBeTrue();
});

it('applies a confirmed manual match as a prior-day match tagged as manually matched', function (): void {
    $payment = enginePayment('P9', '480.00', null, '2026-09-25 09:00:00');
    $sale = priorItem('T48', '480.00', SaleInput::LOOKBACK, '2026-09-22', 12);
    $input = new EngineInput('2026-09-25', [], [], [$payment], enginePosting('T48', '480.00'), eat('2026-09-25 22:00:00'), eat('2026-09-26 00:00:00'), ruleConfig(), [$payment->identity() => $sale]);
    $item = (new ReconciliationEngine)->reconcile($input)->items[0];

    expect($item->status->value)->toBe('MATCHED_PRIOR_DAY')
        ->and($item->ruleId)->toBe('MANUAL')
        ->and($item->tag)->toBe('Paid late (D+3), manually matched')
        ->and($item->flags['manual_match'])->toBeTrue();
});
