<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Contracts\AuditActor;
use Carbon\CarbonImmutable;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;
use Modules\Ingestion\Services\SyntheticDataGenerator;

final class SeedSyntheticData
{
    public function __construct(
        private readonly MockSourceStore $store,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(int $days, int $salesPerDay, int $seed, CarbonImmutable $lastDate, AuditActor|string|null $actor = null): array
    {
        $generator = new SyntheticDataGenerator($seed);
        $dates = [];
        $paymentsByDate = [];
        $timezone = (string) config('reconflow.display_timezone');

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $lastDate->subDays($offset)->toDateString();
            $day = $generator->day($date, $salesPerDay);
            $this->store->replaceDay(SourceType::Sales, $date, $day->sales);
            $this->store->replaceDay(SourceType::Postings, $date, $day->postings);
            foreach ($day->payments as $payment) {
                $paymentsByDate[$this->ownDate($payment, $date, $timezone)][] = $payment;
            }
            $dates[] = $date;
        }
        foreach ($paymentsByDate as $date => $payments) {
            $this->store->replaceDay(SourceType::Payments, $date, $payments);
        }

        $this->audit->record(IngestionAuditAction::SyntheticDataSeeded, $actor, 'mock_source', payload: [
            'dates' => $dates,
            'sales_per_day' => $salesPerDay,
            'seed' => $seed,
        ]);

        return $dates;
    }

    private function ownDate(array $payment, string $generatedFor, string $timezone): string
    {
        $timestamp = (string) ($payment['timestamp'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp) !== 1) {
            return $generatedFor;
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $timestamp, $timezone)?->toDateString() ?? $generatedFor;
    }
}
