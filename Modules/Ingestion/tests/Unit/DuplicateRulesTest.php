<?php

declare(strict_types=1);

use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\BatchValidator;
use Modules\Ingestion\Support\Schema\ValidationContext;

function validateRows(SourceType $source, array $rows, array $existing = []): array
{
    $outcome = app(BatchValidator::class)->validate($source, $rows, ValidationContext::forDate('2026-09-22'), $existing);

    return array_map(fn ($r) => [$r->rowNumber, $r->errors], $outcome->rows);
}

it('keeps the first of two identical sales rows and quarantines the copy', function (): void {
    $row = sale();

    expect(validateRows(SourceType::Sales, [2 => $row, 3 => $row]))->toBe([
        [2, []],
        [3, ['Duplicate row: exact copy of row 2']],
    ]);
});

it('quarantines every row that shares a transaction id with different content', function (): void {
    $rows = [2 => sale(), 3 => sale(['expected_amount' => '99.00']), 4 => sale(['transaction_id' => 'TUP-S-000002'])];

    expect(validateRows(SourceType::Sales, $rows))->toBe([
        [2, ['Conflicting records share transaction_id TUP-S-000001']],
        [3, ['Conflicting records share transaction_id TUP-S-000001']],
        [4, []],
    ]);
});

it('applies duplicate rules to journal ids on postings', function (): void {
    $posting = ['journal_id' => 'JNL-1', 'posting_date' => '2026-09-22', 'transaction_id' => 'TUP-S-000001', 'account' => 'A', 'amount' => '1.00', 'currency' => 'USD', 'status' => 'POSTED'];

    expect(validateRows(SourceType::Postings, [2 => $posting, 3 => [...$posting, 'transaction_id' => 'TUP-S-000009']]))
        ->each->toMatchArray([1 => ['Conflicting records share journal_id JNL-1']]);
});

it('never quarantines duplicate payment receipts because the engine flags them', function (): void {
    $row = payment();

    expect(validateRows(SourceType::Payments, [2 => $row, 3 => $row]))->toBe([[2, []], [3, []]]);
});

it('refuses keys already loaded for the date when appending', function (): void {
    expect(validateRows(SourceType::Sales, [2 => sale()], ['TUP-S-000001' => true]))
        ->toBe([[2, ['transaction_id TUP-S-000001 already exists for this date: use Replace']]]);
});
