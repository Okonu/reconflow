<?php

declare(strict_types=1);

use Modules\DataProtection\Enums\DataClassification;
use Modules\DataProtection\Services\Pseudonymiser;
use Modules\DataProtection\Support\FieldInventory;
use Modules\DataProtection\Support\PersonalData;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('masks phone numbers for display', function (?string $raw, ?string $masked): void {
    expect(PersonalData::maskPhone($raw))->toBe($masked);
})->with([
    ['254700123456', '07•• ••• 456'],
    ['+254700123456', '07•• ••• 456'],
    ['0700123456', '07•• ••• 456'],
    ['254112345678', '01•• ••• 678'],
    ['07001234', '•••34'],
    ['', ''],
    [null, null],
]);

it('pseudonymises identifiers stably, salted and format-insensitively', function (): void {
    $a = new Pseudonymiser('salt-a');
    $token = $a->token('254700123456');

    expect($token)->toStartWith('CUST_')
        ->and($token)->toBe($a->token('+254 700 123 456'))
        ->and($token)->not->toBe((new Pseudonymiser('salt-b'))->token('254700123456'))
        ->and($token)->not->toContain('700123456');
});

it('refuses to pseudonymise without a salt', function (): void {
    new Pseudonymiser('');
})->throws(RuntimeException::class);

it('scrubs phones, bearer tokens, JWTs and API keys from free text', function (): void {
    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl';
    $out = PersonalData::scrubText("call 254700123456 or 0711222333 with Bearer abc.def, {$jwt} and sk-ant-api03-xyz");

    foreach (['254700123456', '0711222333', 'abc.def', $jwt, 'sk-ant-api03-xyz'] as $secret) {
        expect($out)->not->toContain($secret);
    }
});

it('scrubs nested structures and blanks sensitive keys', function (): void {
    $out = PersonalData::scrub(['password' => 'hunter2', 'rows' => [['payer_phone' => '254700123456']], 'n' => 5]);

    expect($out['password'])->toBe('[REDACTED]')
        ->and($out['rows'][0]['payer_phone'])->toBe('07•• ••• 456')
        ->and($out['n'])->toBe(5);
});

it('classifies every column of every upload template', function (string $dataset, string $template): void {
    $sheet = IOFactory::load(base_path("samples/templates/{$template}"))->getSheetByName('Data');
    $headers = array_values(array_filter($sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0]));

    expect($headers)->toEqualCanonicalizing(array_keys(FieldInventory::FIELDS[$dataset]));
    foreach ($headers as $header) {
        expect(FieldInventory::classify($dataset, $header))->toBeInstanceOf(DataClassification::class);
    }
})->with([
    ['sales', 'sales_upload_template.xlsx'],
    ['payments', 'payments_upload_template.xlsx'],
    ['postings', 'erp_postings_upload_template.xlsx'],
]);

it('treats only phone columns as personal data in source datasets', function (): void {
    expect(FieldInventory::personalFields('sales'))->toBe(['customer_phone'])
        ->and(FieldInventory::personalFields('payments'))->toBe(['payer_phone'])
        ->and(FieldInventory::personalFields('postings'))->toBe([]);
});
