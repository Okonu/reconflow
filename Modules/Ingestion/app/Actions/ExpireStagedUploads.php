<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Enums\StagingState;
use Modules\Ingestion\Models\UploadStaging;

final class ExpireStagedUploads
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(): int
    {
        $ids = UploadStaging::query()->expired()->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }
        UploadStaging::query()->whereKey($ids)->update(['state' => StagingState::Expired->value, 'rows' => null]);
        $this->audit->record(IngestionAuditAction::UploadsExpired, entityType: 'upload', payload: [
            'expired_count' => count($ids),
            'upload_ids' => $ids,
        ]);

        return count($ids);
    }
}
