<?php

declare(strict_types=1);

namespace Modules\DataProtection\Actions;

use App\Contracts\AuditActor;
use App\Contracts\PersonalDataStore;
use Illuminate\Contracts\Container\Container;
use Modules\Audit\Services\AuditLogger;
use Modules\DataProtection\Enums\DataProtectionAuditAction;
use Modules\DataProtection\Services\Pseudonymiser;
use Modules\DataProtection\Support\PersonalData;

final class EraseSubject
{
    public function __construct(
        private readonly Container $app,
        private readonly AuditLogger $audit,
        private readonly Pseudonymiser $pseudonymiser,
    ) {}

    public function handle(AuditActor $actor, string $phone, string $reason, string $reference): array
    {
        $variants = PersonalData::phoneVariants($phone);
        $counts = [];
        foreach ($this->app->tagged(PersonalDataStore::TAG) as $store) {
            if ($store instanceof PersonalDataStore) {
                $counts = [...$counts, ...$store->anonymiseSubject($variants, PersonalData::ANONYMISED)];
            }
        }
        $this->audit->record(DataProtectionAuditAction::SubjectErased, $actor, 'data_subject', null, [
            'subject' => $this->pseudonymiser->token($variants[0]),
            'request_reference' => $reference,
            'reason' => $reason,
            'records' => $counts,
        ]);

        return $counts;
    }
}
