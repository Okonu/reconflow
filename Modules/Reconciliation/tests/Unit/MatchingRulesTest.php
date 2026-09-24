<?php

declare(strict_types=1);
use Modules\Reconciliation\Engine\PostingLine;

describe('R1 exact reference and R4 amount check', function (): void {
    it('matches an exact payment and posting', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '77.00', 'T1')], enginePosting('T1', '77.00'));
        expect($r['items'][0])->toMatchArray(['status' => 'MATCHED', 'rule' => 'R1+R4+R6', 'variance' => '0.00']);
    });

    it('treats a difference of exactly the tolerance as a tolerance match and one cent more as a variance', function (string $paid, string $status): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', $paid, 'T1')], enginePosting('T1', '77.00'));
        expect($r['items'][0]['status'])->toBe($status);
    })->with([
        'within' => ['77.25', 'MATCHED_TOLERANCE'],
        'exactly +0.50' => ['77.50', 'MATCHED_TOLERANCE'],
        'exactly -0.50' => ['76.50', 'MATCHED_TOLERANCE'],
        '+0.51' => ['77.51', 'VARIANCE'],
        'under-payment' => ['27.00', 'VARIANCE'],
    ]);

    it('records the variance amount and percentage', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '27.00', 'T1')], enginePosting('T1', '77.00'));
        expect($r['items'][0])->toMatchArray(['status' => 'VARIANCE', 'rule' => 'R4', 'variance' => '-50.00']);
    });
});

describe('R2 split payments', function (): void {
    it('matches instalments that sum to the expected amount', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '27.03', 'T1', '2026-09-22 10:00:00'), enginePayment('P2', '49.97', 'T1', '2026-09-22 11:00:00')], enginePosting('T1', '77.00'));
        expect($r['items'][0])->toMatchArray(['status' => 'MATCHED_SPLIT', 'rule' => 'R2', 'payments' => ['P1', 'P2']]);
    });

    it('reports short instalments as one variance with every payment attached', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '30.00', 'T1', '2026-09-22 10:00:00'), enginePayment('P2', '30.00', 'T1', '2026-09-22 12:00:00')], enginePosting('T1', '77.00'));
        expect($r['items'])->toHaveCount(1)
            ->and($r['items'][0])->toMatchArray(['status' => 'VARIANCE', 'rule' => 'R2+R4', 'variance' => '-17.00', 'payments' => ['P1', 'P2']])
            ->and($r['items'][0]['flags']['split'])->toBeTrue();
    });

    it('reports over-paid instalments as a variance', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '50.00', 'T1', '2026-09-22 10:00:00'), enginePayment('P2', '50.00', 'T1', '2026-09-22 12:00:00')]);
        expect($r['items'][0])->toMatchArray(['status' => 'VARIANCE', 'variance' => '23.00']);
    });

    it('lets variance take precedence over a posting problem', function (): void {
        $r = runEngine([engineSale('T1', '77.00')], [enginePayment('P1', '30.00', 'T1', '2026-09-22 10:00:00'), enginePayment('P2', '30.00', 'T1', '2026-09-22 12:00:00')]);
        expect($r['items'][0]['status'])->toBe('VARIANCE');
    });
});

describe('R3 fuzzy matching', function (): void {
    it('matches an unreferenced payment on phone, amount and time with a confidence score', function (): void {
        $r = runEngine([engineSale('T1', '37.80')], [enginePayment('P1', '37.80', null, '2026-09-22 11:00:00')], enginePosting('T1', '37.80'));
        expect($r['items'][0])->toMatchArray(['status' => 'MATCHED_FUZZY', 'rule' => 'R3'])
            ->and((float) $r['items'][0]['confidence'])->toBeGreaterThan(0.9);
    });

    it('includes a payment exactly 24 hours away and excludes one a second later', function (string $at, string $status): void {
        $r = runEngine([engineSale('T1', '37.80', '2026-09-22 08:00:00')], [enginePayment('P1', '37.80', 'garbled', $at)], enginePosting('T1', '37.80'), config: ['timing_cutoff' => '23:59']);
        expect(statusOf($r, 'T1'))->toBe($status);
    })->with([
        'exactly 24h' => ['2026-09-23 08:00:00', 'MATCHED_FUZZY'],
        '24h and 1s' => ['2026-09-23 08:00:01', 'MISSING_PAYMENT'],
    ]);

    it('requires the amount within tolerance', function (): void {
        $r = runEngine([engineSale('T1', '37.80')], [enginePayment('P1', '38.31', null)]);
        expect(statusOf($r, 'T1'))->toBe('MISSING_PAYMENT');
    });

    it('leaves a tie unmatched: both sales missing and the payment unmatched', function (): void {
        $r = runEngine(
            [engineSale('T57', '77.00', '2026-09-22 09:20:04', '254700766177'), engineSale('T58', '77.00', '2026-09-22 09:40:04', '254700766177')],
            [enginePayment('P1', '77.00', null, '2026-09-22 11:20:04', '254700766177')],
        );
        expect(collect($r['items'])->map(fn ($i) => [$i['txn'], $i['status'], $i['rule']])->all())->toEqualCanonicalizing([
            ['T57', 'MISSING_PAYMENT', 'R3 tie → R7'],
            ['T58', 'MISSING_PAYMENT', 'R3 tie → R7'],
            [null, 'UNMATCHED_PAYMENT', 'R3 tie → R7'],
        ]);
    });

    it('leaves a sale with two candidate payments unmatched', function (): void {
        $r = runEngine([engineSale('T1', '10.00')], [enginePayment('P1', '10.00', null, '2026-09-22 11:00:00'), enginePayment('P2', '10.00', null, '2026-09-22 12:00:00')]);
        expect(statusOf($r, 'T1'))->toBe('MISSING_PAYMENT')
            ->and(collect($r['items'])->where('status', 'UNMATCHED_PAYMENT')->count())->toBe(2);
    });
});

describe('R5 duplicates', function (): void {
    it('flags the second copy of the same receipt', function (): void {
        $r = runEngine([engineSale('T1', '19.60')], [enginePayment('SVARJI8QLH', '19.60', 'T1'), enginePayment('SVARJI8QLH', '19.60', 'T1')], enginePosting('T1', '19.60'));
        expect(collect($r['items'])->pluck('status')->all())->toEqualCanonicalizing(['DUPLICATE_PAYMENT', 'MATCHED'])
            ->and(collect($r['items'])->firstWhere('status', 'DUPLICATE_PAYMENT')['txn'])->toBe('T1');
    });

    it('flags the same reference and amount within five minutes, inclusive, but not after', function (string $second, int $duplicates): void {
        $r = runEngine([engineSale('T1', '192.50')], [enginePayment('P1', '192.50', 'T1', '2026-09-22 07:36:58'), enginePayment('P2', '192.50', 'T1', $second)], enginePosting('T1', '192.50'));
        expect(collect($r['items'])->where('status', 'DUPLICATE_PAYMENT')->count())->toBe($duplicates);
    })->with([
        'exactly 5 min' => ['2026-09-22 07:41:58', 1],
        '5 min 1 s' => ['2026-09-22 07:41:59', 0],
    ]);
});

describe('R6 posting check', function (): void {
    it('reports missing, mismatched and duplicate postings', function (): void {
        $postings = enginePosting('T2', '10.00') + enginePosting('T3', '5.00') + ['T4' => [
            new PostingLine('JNL-A', 'T4', 1000),
            new PostingLine('JNL-B', 'T4', 1000),
        ]];
        $r = runEngine(
            [engineSale('T1', '10.00'), engineSale('T2', '10.00'), engineSale('T3', '6.65'), engineSale('T4', '10.00')],
            [enginePayment('P1', '10.00', 'T1'), enginePayment('P2', '10.00', 'T2'), enginePayment('P3', '6.65', 'T3'), enginePayment('P4', '10.00', 'T4')],
            $postings,
        );
        expect([statusOf($r, 'T1'), statusOf($r, 'T2'), statusOf($r, 'T3'), statusOf($r, 'T4')])
            ->toBe(['MISSING_POSTING', 'MATCHED', 'POSTING_MISMATCH', 'DUPLICATE_POSTING']);
    });

    it('compares the posted amount with the expected amount exactly', function (): void {
        $r = runEngine([engineSale('T1', '10.00')], [enginePayment('P1', '10.00', 'T1')], enginePosting('T1', '10.01'));
        expect(statusOf($r, 'T1'))->toBe('POSTING_MISMATCH');
    });
});

describe('R7 leftovers and the timing window', function (): void {
    it('treats a sale at exactly 22:00:00 as pending timing and one second earlier as missing', function (string $at, string $status): void {
        $r = runEngine([engineSale('T1', '28.00', $at)], []);
        expect(statusOf($r, 'T1'))->toBe($status);
    })->with([
        '21:59:59' => ['2026-09-22 21:59:59', 'MISSING_PAYMENT'],
        '22:00:00' => ['2026-09-22 22:00:00', 'PENDING_TIMING'],
        '22:27:00' => ['2026-09-22 22:27:00', 'PENDING_TIMING'],
    ]);

    it('reports a payment with no sale as unmatched', function (): void {
        $r = runEngine([], [enginePayment('BNK1', '15.00', 'ACC 5521', '2026-09-22 08:02:37')]);
        expect($r['items'][0])->toMatchArray(['status' => 'UNMATCHED_PAYMENT', 'rule' => 'R7', 'txn' => null]);
    });

    it('matches a grace-window payment to the day but never reports an unmatched grace payment on it', function (): void {
        $r = runEngine(
            [engineSale('T59', '124.00', '2026-09-22 21:40:00')],
            [enginePayment('P59', '124.00', 'T59', '2026-09-23 01:15:00'), enginePayment('LATE', '50.00', 'nothing', '2026-09-23 02:00:00')],
            enginePosting('T59', '124.00'),
        );
        expect(statusOf($r, 'T59'))->toBe('MATCHED')
            ->and(collect($r['items'])->pluck('payments')->flatten()->all())->not->toContain('LATE');
    });

    it('reports a payment at 23:59:59 on the day and leaves one at midnight to the next day', function (): void {
        $r = runEngine([], [enginePayment('A', '5.00', null, '2026-09-22 23:59:59'), enginePayment('B', '5.00', null, '2026-09-23 00:00:00')]);
        expect(collect($r['items'])->pluck('payments')->flatten()->all())->toBe(['A']);
    });
});

it('is deterministic: the same input always produces the same output', function (): void {
    $sales = [engineSale('T1', '10.00'), engineSale('T2', '20.00', '2026-09-22 22:30:00')];
    $payments = [enginePayment('P1', '10.00', 'T1'), enginePayment('P2', '5.00', null)];

    expect(runEngine($sales, $payments))->toBe(runEngine($sales, $payments));
});
