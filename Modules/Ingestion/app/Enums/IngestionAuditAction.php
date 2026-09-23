<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum IngestionAuditAction: string
{
    case UploadStaged = 'upload.staged';
    case UploadConfirmed = 'upload.confirmed';
    case UploadCancelled = 'upload.cancelled';
    case UploadsExpired = 'upload.expired';
    case BatchIngested = 'batch.ingested';
    case BatchSuperseded = 'batch.superseded';
    case DemoReset = 'demo.reset';
    case SyntheticDataSeeded = 'demo.seeded';
}
