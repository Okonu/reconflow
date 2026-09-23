<?php

declare(strict_types=1);

use Modules\Ingestion\Enums\SourceType;

it('reports each answer-key quarantine reason with its exact wording', function (SourceType $source, array $row, array $errors): void {
    expect(errorsFor($source, $row))->toBe($errors);
})->with([
    'missing id' => [SourceType::Sales, sale(['transaction_id' => null, 'payment_reference' => null]), ['Missing required field: transaction_id']],
    'negative amount' => [SourceType::Sales, sale(['expected_amount' => '-31']), ['Amount must be greater than 0']],
    'bad phone' => [SourceType::Sales, sale(['customer_phone' => '07001234']), ['Invalid phone format (expected 2547XXXXXXXX)']],
    'bad timestamp' => [SourceType::Payments, payment(['timestamp' => '22/09/2026 25:61', 'reference' => null]), ['Unparseable timestamp']],
    'cash channel' => [SourceType::Payments, payment(['channel' => 'CASH', 'reference' => null]), ["Invalid channel 'CASH'"]],
]);

it('rejects unsupported currency on postings', function (): void {
    $row = ['journal_id' => 'JNL-2026-999001', 'posting_date' => '2026-09-22', 'transaction_id' => 'TUP-S-000961', 'account' => '4000-SALES-CASH', 'amount' => '4500', 'currency' => 'KES', 'status' => 'POSTED'];

    expect(errorsFor(SourceType::Postings, $row))->toBe(["Unsupported currency 'KES' (USD only)"]);
});

it('quarantines a sale whose business date differs from the batch date', function (): void {
    expect(errorsFor(SourceType::Sales, sale(['business_date' => '2026-09-21'])))
        ->toBe(['Business date 2026-09-21 does not match the batch date 2026-09-22']);
});

it('accepts payments inside the extract window including the grace hours and rejects those outside', function (string $timestamp, bool $ok): void {
    expect(errorsFor(SourceType::Payments, payment(['timestamp' => $timestamp])) === [])->toBe($ok);
})->with([
    'start of day' => ['2026-09-22 00:00:00', true],
    'late night grace' => ['2026-09-23 01:15:00', true],
    'last grace second' => ['2026-09-23 05:59:59', true],
    'window end is exclusive' => ['2026-09-23 06:00:00', false],
    'previous day' => ['2026-09-21 23:59:59', false],
]);

it('explains an out-of-window payment', function (): void {
    expect(errorsFor(SourceType::Payments, payment(['timestamp' => '2026-09-23 07:00:00'])))
        ->toBe(['Payment timestamp 2026-09-23 07:00:00 is outside the extract window (2026-09-22 00:00 to 2026-09-23 06:00 EAT)']);
});

it('requires a payer phone for mobile money but not for bank payments', function (): void {
    expect(errorsFor(SourceType::Payments, payment(['payer_phone' => null])))->toBe(['Missing required field: payer_phone'])
        ->and(errorsFor(SourceType::Payments, payment(['payer_phone' => null, 'channel' => 'BANK', 'payment_id' => 'BNK1'])))->toBe([]);
});
