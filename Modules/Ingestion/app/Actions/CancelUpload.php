<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Exceptions\DomainException;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\StagingState;
use Modules\Ingestion\Models\UploadStaging;
use Modules\Users\Models\User;

final class CancelUpload
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, UploadStaging $staging): void
    {
        if ($staging->state !== StagingState::Staged) {
            throw DomainException::conflict('This upload has already been '.$staging->state->value.'.');
        }
        $staging->update(['state' => StagingState::Cancelled, 'rows' => null]);
        $this->audit->record(IngestionAuditAction::UploadCancelled, $user, 'upload', $staging->id, [
            'source' => $staging->source->value,
            'business_date' => $staging->business_date->toDateString(),
        ]);
    }
}
