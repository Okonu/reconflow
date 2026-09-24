<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
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
