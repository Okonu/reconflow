<?php

declare(strict_types=1);

namespace Modules\Audit\Console\Commands;

use Illuminate\Console\Command;
use Modules\Audit\Actions\ArchiveExpiredAuditEvents;

final class ArchiveAuditEventsCommand extends Command
{
    protected $signature = 'audit:archive';

    protected $description = 'Archive audit events older than the retention period and write a checkpoint';

    public function handle(ArchiveExpiredAuditEvents $archive): int
    {
        $result = $archive->handle();
        $this->info($result->archivedCount === 0 ? 'No audit events past retention.' : "Archived {$result->archivedCount} events to {$result->file}");

        return self::SUCCESS;
    }
}
