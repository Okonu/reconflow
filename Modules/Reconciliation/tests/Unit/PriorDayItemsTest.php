<?php

declare(strict_types=1);

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
