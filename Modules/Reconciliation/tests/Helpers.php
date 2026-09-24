<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Reconciliation\Actions\ReconcileDate;
use Modules\Reconciliation\DTOs\RuleConfig;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\PaymentInput;
use Modules\Reconciliation\Engine\PostingLine;
use Modules\Reconciliation\Engine\ReconciliationEngine;
use Modules\Reconciliation\Engine\SaleInput;
use Modules\Reconciliation\Enums\RunTrigger;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Support\Cents;
use PhpOffice\PhpSpreadsheet\IOFactory;

function reconcile(string $date = '2026-09-22', bool $refresh = false): ReconRun
{
    return app(ReconcileDate::class)->handle(new RunRequest($date, RunTrigger::Manual, $refresh), 'test');
}

function importAnswerKeyFiles(string $set, string $date = '2026-09-22'): void
{
    $suffix = $set === 'volume' ? '_volume' : '';
    test()->actingAs(demoUser('analyst@demo'));
    foreach (['sales' => 'sales', 'payments' => 'payments', 'postings' => 'erp_postings'] as $source => $prefix) {
        $staging = stageFile(samplePath("{$set}/{$prefix}_2026-09-22{$suffix}.xlsx"), $source, $date);
        test()->post(route('ingestion.uploads.confirm', $staging), ['mode' => 'replace'])->assertRedirect();
    }
}

function answerKeyItems(string $set): array
{
    $suffix = $set === 'volume' ? '_volume' : '';
    $sheet = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->load(samplePath("{$set}/expected_results_2026-09-22{$suffix}.xlsx"))->getSheetByName('Expected results');
    $items = [];
    foreach (array_slice($sheet->toArray(null, false, false), 1) as $row) {
        if ($row[7] === null) {
            continue;
        }
        $payments = $row[1] === null ? [] : array_map('trim', explode(',', (string) $row[1]));
        sort($payments);
        $items[] = implode(' | ', [(string) $row[0], implode(',', $payments), $row[7], $row[9]]);
    }
    sort($items);

    return $items;
}

function runItems(ReconRun $run): array
{
    $items = $run->results()->where('section', 'current')->get()->map(function ($r) {
        $payments = $r->payment_ids;
        sort($payments);

        return implode(' | ', [(string) $r->transaction_id, implode(',', $payments), $r->status->value, $r->rule_id]);
    })->all();
    sort($items);

    return $items;
}

function ruleConfig(array $overrides = []): RuleConfig
{
    return RuleConfig::fromArray([
        'tolerance' => '0.50', 'fuzzy_window_hours' => 24, 'timing_cutoff' => '22:00', 'duplicate_window_minutes' => 5,
        'grace_hours' => 6, 'timing_carry_days' => 1, 'late_payment_lookback_days' => 7, 'schedule_time' => '06:00',
        'blocked_retry_minutes' => 30, 'blocked_max_attempts' => 12, 'timezone' => 'Africa/Nairobi', ...$overrides,
    ]);
}

function eat(string $datetime): int
{
    return CarbonImmutable::parse($datetime, 'Africa/Nairobi')->getTimestamp();
}

function engineSale(string $id, string $amount, string $at = '2026-09-22 10:00:00', string $phone = '254700000001', array $extra = []): SaleInput
{
    return new SaleInput(...array_merge([
        'key' => 's:'.$id, 'transactionId' => $id, 'soldAt' => eat($at), 'phone' => $phone,
        'expectedCents' => Cents::fromDecimal($amount), 'reference' => $id,
    ], $extra));
}

function enginePayment(string $id, string $amount, ?string $reference, string $at = '2026-09-22 10:30:00', ?string $phone = '254700000001'): PaymentInput
{
    static $n = 0;
    $n++;

    return new PaymentInput('p:'.$n.':'.$id, $id, eat($at), $phone, Cents::fromDecimal($amount), $reference, substr($at, 0, 10));
}

function enginePosting(string $transactionId, string $amount, string $journal = 'JNL-1'): array
{
    return [$transactionId => [new PostingLine($journal, $transactionId, Cents::fromDecimal($amount))]];
}

function runEngine(array $sales, array $payments, array $postings = [], array $prior = [], array $config = [], string $date = '2026-09-22'): array
{
    $input = new EngineInput(
        businessDate: $date,
        sales: $sales,
        priorItems: $prior,
        payments: $payments,
        postingsByTransaction: $postings,
        timingCutoffAt: eat($date.' '.($config['timing_cutoff'] ?? '22:00').':00'),
        dayEndsAt: eat(CarbonImmutable::parse($date)->addDay()->toDateString().' 00:00:00'),
        config: ruleConfig($config),
    );
    $output = (new ReconciliationEngine)->reconcile($input);

    return ['items' => array_map(fn ($i) => [
        'section' => $i->section->value,
        'txn' => $i->transactionId(),
        'payments' => $i->paymentIds(),
        'status' => $i->status->value,
        'rule' => $i->ruleId,
        'variance' => Cents::toDecimal($i->varianceCents()),
        'tag' => $i->tag,
        'flags' => $i->flags,
        'confidence' => $i->confidence,
    ], $output->items), 'escalations' => $output->escalations];
}

function statusOf(array $result, ?string $txn, ?string $payment = null): ?string
{
    foreach ($result['items'] as $item) {
        if ($item['txn'] === $txn && ($payment === null || in_array($payment, $item['payments'], true))) {
            return $item['status'];
        }
    }

    return null;
}

function seedPendingSale(): array
{
    app(SeedDemoData::class)->handle(1, 20, 5, '2026-09-21', ingest: false);
    DB::table('mock_source_rows')->where('source', 'payments')->delete();
    $sale = ['transaction_id' => 'TUP-S-990001', 'business_date' => '2026-09-21', 'timestamp' => '2026-09-21 22:30:00', 'agent_id' => 'AG-001', 'customer_phone' => '254700990001', 'region' => 'Coast', 'product_sku' => 'SOLAR-LAMP-S1', 'expected_amount' => '28.00', 'currency' => 'USD', 'payment_reference' => 'TUP-S-990001'];
    $posting = ['journal_id' => 'JNL-2026-990001', 'posting_date' => '2026-09-21', 'transaction_id' => 'TUP-S-990001', 'account' => '4000-SALES-CASH', 'amount' => '28.00', 'currency' => 'USD', 'status' => 'POSTED'];
    $store = app(MockSourceStore::class);
    $store->replaceDay(SourceType::Sales, '2026-09-21', [$sale]);
    $store->replaceDay(SourceType::Postings, '2026-09-21', [$posting]);
    $store->replaceDay(SourceType::Payments, '2026-09-21', [['payment_id' => 'SUNRELATED1', 'timestamp' => '2026-09-21 09:00:00', 'channel' => 'BANK', 'payer_phone' => '254700111111', 'amount' => '5.00', 'currency' => 'USD', 'reference' => 'ACC 1']]);
    $store->replaceDay(SourceType::Sales, '2026-09-22', [[...$sale, 'transaction_id' => 'TUP-S-990002', 'payment_reference' => 'TUP-S-990002', 'business_date' => '2026-09-22', 'timestamp' => '2026-09-22 10:00:00']]);
    $store->replaceDay(SourceType::Postings, '2026-09-22', [[...$posting, 'journal_id' => 'JNL-2026-990002', 'transaction_id' => 'TUP-S-990002', 'posting_date' => '2026-09-22']]);

    return [$sale, $store];
}

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
