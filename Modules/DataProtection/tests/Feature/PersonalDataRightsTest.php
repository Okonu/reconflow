<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\AuditEvent;
use Modules\Ingestion\Models\SalesRecord;

it('unmasks one record for a stated purpose and audits it', function (): void {
    importAnswerKeyFiles('golden');
    $sale = SalesRecord::query()->firstOrFail();

    $this->actingAs(demoUser('auditor@demo'))->postJson(route('pii.unmask'), ['dataset' => 'sales', 'record_id' => $sale->id, 'purpose' => 'Call customer'])->assertForbidden();
    $this->actingAs(demoUser('analyst@demo'))->postJson(route('pii.unmask'), ['dataset' => 'sales', 'record_id' => $sale->id])->assertUnprocessable();
    $this->actingAs(demoUser('analyst@demo'))->postJson(route('pii.unmask'), ['dataset' => 'sales', 'record_id' => $sale->id, 'purpose' => 'Call customer about variance'])
        ->assertOk()->assertJsonPath('values.customer_phone', $sale->customer_phone);

    $event = AuditEvent::query()->where('action', 'pii.unmasked')->sole();
    expect($event->payload)->toMatchArray(['fields' => ['customer_phone'], 'purpose' => 'Call customer about variance'])
        ->and(json_encode($event->payload))->not->toContain($sale->customer_phone);
});

it('anonymises every copy of a data subject on an erasure request and audits a token, not the number', function (): void {
    importAnswerKeyFiles('golden');
    $phone = (string) SalesRecord::query()->value('customer_phone');
    $local = '0'.substr($phone, 3);

    $this->actingAs(demoUser('manager@demo'))->postJson(route('privacy.erasures.store'), ['phone' => $local, 'reason' => 'Data subject request by email', 'request_reference' => 'DSR-1'])->assertForbidden();
    $response = $this->actingAs(demoUser('admin@demo'))->postJson(route('privacy.erasures.store'), ['phone' => $local, 'reason' => 'Data subject request by email', 'request_reference' => 'DSR-1'])->assertOk();

    expect($response->json('total'))->toBeGreaterThan(0)
        ->and(SalesRecord::query()->where('customer_phone', $phone)->exists())->toBeFalse()
        ->and(DB::table('payment_records')->where('payer_phone', $phone)->exists())->toBeFalse()
        ->and(DB::table('mock_source_rows')->whereRaw("payload->>'customer_phone' = ?", [$phone])->exists())->toBeFalse();
    $event = AuditEvent::query()->where('action', 'privacy.subject_erased')->sole();
    expect(json_encode($event->payload))->not->toContain($phone)->not->toContain($local)
        ->and($event->payload['subject'])->toStartWith('CUST_');
});

it('anonymises personal data older than the retention period and leaves newer data alone', function (): void {
    importAnswerKeyFiles('golden');
    config(['dataprotection.retention.transactions_years' => 7]);

    $this->artisan('reconflow:anonymise-expired')->assertSuccessful();
    expect(SalesRecord::query()->where('customer_phone', 'ANONYMISED')->count())->toBe(0);

    $this->travelTo(now()->addYears(8));
    $this->artisan('reconflow:anonymise-expired')->assertSuccessful();
    expect(SalesRecord::query()->where('customer_phone', '<>', 'ANONYMISED')->count())->toBe(0)
        ->and(DB::table('quarantined_rows')->whereRaw("raw->>'customer_phone' ~ '^[0-9+]'")->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'retention.transactions_anonymised')->exists())->toBeTrue();
});
