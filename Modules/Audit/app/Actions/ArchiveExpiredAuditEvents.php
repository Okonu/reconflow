<?php

declare(strict_types=1);

namespace Modules\Audit\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\DTOs\ArchiveResult;
use Modules\Audit\Services\AuditArchiver;

final class ArchiveExpiredAuditEvents
{
    public function __construct(private readonly AuditArchiver $archiver) {}

    public function handle(?CarbonImmutable $now = null): ArchiveResult
    {
        return $this->archiver->archiveExpired(
            (int) config('audit.retention_years'),
            (string) config('audit.archive_path'),
            $now,
        );
    }
}
