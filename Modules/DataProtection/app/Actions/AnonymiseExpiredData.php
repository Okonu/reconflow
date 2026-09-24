<?php

declare(strict_types=1);

namespace Modules\DataProtection\Actions;

use App\Contracts\PersonalDataStore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Modules\Audit\Services\AuditLogger;
use Modules\DataProtection\Enums\DataProtectionAuditAction;
use Modules\DataProtection\Support\PersonalData;

final class AnonymiseExpiredData
{
    public function __construct(
        private readonly Container $app,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(?CarbonImmutable $now = null): array
    {
        $years = (int) config('dataprotection.retention.transactions_years');
        $cutoff = ($now ?? CarbonImmutable::now())->subYears($years)->toDateString();
        $counts = [];
        foreach ($this->app->tagged(PersonalDataStore::TAG) as $store) {
            if ($store instanceof PersonalDataStore) {
                $counts = [...$counts, ...$store->anonymiseBefore($cutoff, PersonalData::ANONYMISED)];
            }
        }
        if (array_sum($counts) > 0) {
            $this->audit->record(DataProtectionAuditAction::RetentionAnonymised, null, 'retention', null, ['before' => $cutoff, 'years' => $years, 'records' => $counts]);
        }

        return ['before' => $cutoff, 'records' => $counts];
    }
}
