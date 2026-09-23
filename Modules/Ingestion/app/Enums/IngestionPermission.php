<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

use App\Contracts\PermissionEnum;
use App\Support\Authorization\DefaultRole;

enum IngestionPermission: string implements PermissionEnum
{
    case ViewUploads = 'uploads.view';
    case CreateUploads = 'uploads.create';
    case ViewBatches = 'batches.view';
    case ResetDemo = 'demo.reset';

    public function description(): string
    {
        return match ($this) {
            self::ViewUploads => 'View upload history and download templates and the sample test pack',
            self::CreateUploads => 'Upload, preview, confirm and cancel source files',
            self::ViewBatches => 'View ingested source batches and data-quality reports',
            self::ResetDemo => 'Reset demo data (truncate operational data and re-seed)',
        };
    }

    public function group(): string
    {
        return 'Data ingestion';
    }

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewUploads, self::ViewBatches => DefaultRole::cases(),
            self::CreateUploads => [DefaultRole::ReconAnalyst, DefaultRole::FinanceManager],
            self::ResetDemo => [DefaultRole::Administrator],
        };
    }
}
