<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Ingestion\DTOs\GeneratedDay;
use Modules\Ingestion\Enums\Region;
use Modules\Ingestion\Support\ProductCatalogue;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class SyntheticDataGenerator
{
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const ALPHANUMERIC = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private Randomizer $random;

    private array $rates;

    private array $usedReceipts = [];

    private int $transactionSequence;

    private int $journalSequence;

    public function __construct(int $seed, int $firstTransaction = 100001, int $firstJournal = 100001, ?array $rates = null)
    {
        $this->random = new Randomizer(new Mt19937($seed));
        $this->rates = $rates ?? (array) config('ingestion.generator.rates');
        $this->transactionSequence = $firstTransaction;
        $this->journalSequence = $firstJournal;
    }

    public function day(string $businessDate, int $salesCount): GeneratedDay
    {
        $timezone = (string) config('reconflow.display_timezone');
        $date = CarbonImmutable::parse($businessDate, $timezone)->startOfDay();
        $sales = [];
        $payments = [];
        $postings = [];

        for ($i = 0; $i < $salesCount; $i++) {
            $sale = $this->sale($date);
            $sales[] = $sale;
            array_push($payments, ...$this->paymentsFor($sale, $date));
            $posting = $this->postingFor($sale, $date);
            if ($posting !== null) {
                $postings[] = $posting;
            }
        }

        for ($i = 0, $n = $this->count($salesCount, 'unknown_payment'); $i < $n; $i++) {
            $payments[] = $this->unknownPayment($date);
        }
        $this->injectMalformed($date, $salesCount, $sales, $payments, $postings);

        usort($sales, fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));
        usort($payments, fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp']));

        return new GeneratedDay($date->toDateString(), $sales, $payments, $postings);
    }

    private function sale(CarbonImmutable $date): array
    {
        $sku = $this->pick(array_keys(ProductCatalogue::PRODUCTS));
        $quantity = $this->quantity($sku);
        $amount = Money::of(ProductCatalogue::PRODUCTS[$sku])->multipliedBy($quantity);
        $id = sprintf('TUP-S-%06d', $this->transactionSequence++);

        return [
            'transaction_id' => $id,
            'business_date' => $date->toDateString(),
            'timestamp' => $date->addSeconds($this->saleSecond())->format(self::TIME_FORMAT),
            'agent_id' => sprintf('AG-%03d', $this->random->getInt(1, 60)),
            'customer_phone' => $this->phone(),
            'region' => $this->pick(array_column(Region::cases(), 'value')),
            'product_sku' => $sku,
            'expected_amount' => $this->format($amount),
            'currency' => 'USD',
            'payment_reference' => $id,
        ];
    }

    private function paymentsFor(array $sale, CarbonImmutable $date): array
    {
        $roll = $this->random->nextFloat();
        $amount = Money::of($sale['expected_amount']);
        $saleAt = CarbonImmutable::parse($sale['timestamp'], $date->getTimezone());
        $paidAt = $saleAt->addSeconds($this->random->getInt(60, 90 * 60));
        $base = fn (string $paymentAmount, CarbonImmutable $at, ?string $reference) => $this->payment($sale['customer_phone'], $paymentAmount, $at, $reference);

        $bands = $this->bands(['missing_payment', 'amount_mismatch', 'rounding_difference', 'reference_problem', 'split_payment', 'duplicate_payment']);

        return match ($this->band($roll, $bands)) {
            'missing_payment' => [],
            'amount_mismatch' => [$base($this->format($this->mismatch($amount)), $paidAt, $sale['transaction_id'])],
            'rounding_difference' => [$base($this->format($this->rounding($amount)), $paidAt, $sale['transaction_id'])],
            'reference_problem' => [$base($sale['expected_amount'], $paidAt, $this->brokenReference($sale['transaction_id']))],
            'split_payment' => $this->split($sale, $amount, $paidAt),
            'duplicate_payment' => $this->duplicate($base($sale['expected_amount'], $paidAt, $sale['transaction_id'])),
            default => [$base($sale['expected_amount'], $this->lateIfAfterCutOff($saleAt, $paidAt, $date), $sale['transaction_id'])],
        };
    }

    private function lateIfAfterCutOff(CarbonImmutable $saleAt, CarbonImmutable $paidAt, CarbonImmutable $date): CarbonImmutable
    {
        if ($saleAt->hour < 22 || $this->random->nextFloat() >= 0.3) {
            return $paidAt;
        }

        return $date->addDay()->addHours(7)->addSeconds($this->random->getInt(0, 4 * 3600));
    }

    private function postingFor(array $sale, CarbonImmutable $date): ?array
    {
        $roll = $this->random->nextFloat();
        $bands = $this->bands(['missing_posting', 'posting_mismatch']);
        $choice = $this->band($roll, $bands);
        if ($choice === 'missing_posting') {
            return null;
        }
        $amount = Money::of($sale['expected_amount']);
        if ($choice === 'posting_mismatch') {
            $amount = $amount->plus(Money::of((string) $this->random->getInt(1, 40)));
        }

        return [
            'journal_id' => sprintf('JNL-%s-%06d', $date->format('Y'), $this->journalSequence++),
            'posting_date' => $date->toDateString(),
            'transaction_id' => $sale['transaction_id'],
            'account' => '4000-SALES-CASH',
            'amount' => $this->format($amount),
            'currency' => 'USD',
            'status' => 'POSTED',
        ];
    }

    private function payment(string $phone, string $amount, CarbonImmutable $at, ?string $reference): array
    {
        $bank = $this->random->nextFloat() < 0.15;

        return [
            'payment_id' => $bank ? $this->bankReceipt() : $this->mpesaReceipt(),
            'timestamp' => $at->format(self::TIME_FORMAT),
            'channel' => $bank ? 'BANK' : 'MOBILE_MONEY',
            'payer_phone' => $phone,
            'amount' => $amount,
            'currency' => 'USD',
            'reference' => $reference,
        ];
    }

    private function split(array $sale, BigDecimal $amount, CarbonImmutable $paidAt): array
    {
        $parts = $this->random->getInt(2, 3);
        $cents = $amount->multipliedBy(100)->toInt();
        $shares = [];
        $remaining = $cents;
        for ($i = 1; $i < $parts; $i++) {
            $share = max(1, intdiv($remaining * $this->random->getInt(30, 60), 100));
            $shares[] = $share;
            $remaining -= $share;
        }
        $shares[] = $remaining;
        $payments = [];
        foreach ($shares as $index => $share) {
            $payments[] = $this->payment(
                $sale['customer_phone'],
                $this->format(BigDecimal::of($share)->dividedBy(100, 2)),
                $paidAt->addMinutes($index * $this->random->getInt(10, 50)),
                $sale['transaction_id'],
            );
        }

        return $payments;
    }

    private function duplicate(array $payment): array
    {
        if ($this->random->nextFloat() < 0.5) {
            return [$payment, $payment];
        }
        $second = $payment;
        $second['payment_id'] = $payment['channel'] === 'BANK' ? $this->bankReceipt() : $this->mpesaReceipt();
        $second['timestamp'] = CarbonImmutable::parse($payment['timestamp'])->addSeconds($this->random->getInt(30, 240))->format(self::TIME_FORMAT);

        return [$payment, $second];
    }

    private function unknownPayment(CarbonImmutable $date): array
    {
        $amount = Money::of((string) $this->random->getInt(5, 900));
        $reference = $this->random->nextFloat() < 0.5 ? null : 'ACC '.$this->random->getInt(1000, 9999);

        return $this->payment($this->phone(), $this->format($amount), $date->addSeconds($this->random->getInt(7 * 3600, 21 * 3600)), $reference);
    }

    private function injectMalformed(CarbonImmutable $date, int $salesCount, array &$sales, array &$payments, array &$postings): void
    {
        $n = $this->count($salesCount, 'malformed_row');
        for ($i = 0; $i < $n; $i++) {
            $kind = $i % 6;
            if ($kind < 3) {
                $row = $this->sale($date);
                match ($kind) {
                    0 => $row['transaction_id'] = null,
                    1 => $row['expected_amount'] = '-'.$row['expected_amount'],
                    default => $row['customer_phone'] = '0700'.$this->random->getInt(1000, 9999),
                };
                $sales[] = $row;
            } elseif ($kind < 5) {
                $row = $this->payment($this->phone(), $this->format(Money::of((string) $this->random->getInt(5, 300))), $date->addHours(12), null);
                if ($kind === 3) {
                    $row['timestamp'] = $date->format('d/m/Y').' 25:61';
                } else {
                    $row['channel'] = 'CASH';
                }
                $payments[] = $row;
            } else {
                $postings[] = [
                    'journal_id' => sprintf('JNL-%s-9%05d', $date->format('Y'), $this->journalSequence++ % 100000),
                    'posting_date' => $date->toDateString(),
                    'transaction_id' => sprintf('TUP-S-%06d', $this->transactionSequence++),
                    'account' => '4000-SALES-CASH',
                    'amount' => $this->format(Money::of((string) $this->random->getInt(1000, 9000))),
                    'currency' => 'KES',
                    'status' => 'POSTED',
                ];
            }
        }
    }

    private function mismatch(BigDecimal $amount): BigDecimal
    {
        $factor = BigDecimal::of($this->random->getInt(10, 60))->dividedBy(100, 2);
        $delta = $amount->multipliedBy($factor)->toScale(2, RoundingMode::HalfUp);
        $delta = $delta->isLessThan('1.00') ? BigDecimal::of('1.00') : $delta;

        return $this->random->nextFloat() < 0.75 && $amount->isGreaterThan($delta) ? $amount->minus($delta) : $amount->plus($delta);
    }

    private function rounding(BigDecimal $amount): BigDecimal
    {
        return $amount->plus(BigDecimal::of($this->random->getInt(5, 45))->dividedBy(100, 2));
    }

    private function brokenReference(string $transactionId): ?string
    {
        return match ($this->random->getInt(0, 2)) {
            0 => null,
            1 => substr($transactionId, 0, -1),
            default => str_replace('-', '', $transactionId),
        };
    }

    private function quantity(string $sku): int
    {
        $max = match (true) {
            str_starts_with($sku, 'SOLAR-HOME') => 3,
            str_starts_with($sku, 'FERT') => 20,
            str_starts_with($sku, 'TREE') => 60,
            default => 30,
        };
        $u = $this->random->nextFloat();

        return max(1, min($max, (int) ceil($max * $u * $u * $u)));
    }

    private function saleSecond(): int
    {
        $u = ($this->random->nextFloat() + $this->random->nextFloat()) / 2;

        return 7 * 3600 + (int) ($u * (15 * 3600 + 55 * 60));
    }

    private function phone(): string
    {
        return '254700'.sprintf('%06d', $this->random->getInt(0, 999999));
    }

    private function mpesaReceipt(): string
    {
        do {
            $id = 'S'.$this->random->getBytesFromString(self::ALPHANUMERIC, 9);
        } while (isset($this->usedReceipts[$id]));
        $this->usedReceipts[$id] = true;

        return $id;
    }

    private function bankReceipt(): string
    {
        do {
            $id = 'BNK'.sprintf('%09d', $this->random->getInt(0, 999999999));
        } while (isset($this->usedReceipts[$id]));
        $this->usedReceipts[$id] = true;

        return $id;
    }

    private function bands(array $keys): array
    {
        $bands = [];
        $upper = 0.0;
        foreach ($keys as $key) {
            $upper += (float) ($this->rates[$key] ?? 0);
            $bands[$key] = $upper;
        }

        return $bands;
    }

    private function band(float $roll, array $bands): ?string
    {
        foreach ($bands as $key => $upper) {
            if ($roll < $upper) {
                return $key;
            }
        }

        return null;
    }

    private function count(int $salesCount, string $rate): int
    {
        return (int) round($salesCount * (float) ($this->rates[$rate] ?? 0));
    }

    private function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    private function format(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2, RoundingMode::HalfUp);
    }
}
