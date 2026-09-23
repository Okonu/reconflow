<?php

declare(strict_types=1);

namespace Modules\Ingestion\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ingestion\Actions\ExpireStagedUploads;

final class PurgeStagedUploadsCommand extends Command
{
    protected $signature = 'ingestion:purge-staging';

    protected $description = 'Expire staged uploads older than the staging time-to-live';

    public function handle(ExpireStagedUploads $expire): int
    {
        $this->info("Expired {$expire->handle()} staged uploads.");

        return self::SUCCESS;
    }
}
