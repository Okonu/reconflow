<?php

declare(strict_types=1);

namespace Modules\DataProtection\Console\Commands;

use Illuminate\Console\Command;
use Modules\DataProtection\Actions\AnonymiseExpiredData;

final class AnonymiseExpiredCommand extends Command
{
    protected $signature = 'reconflow:anonymise-expired';

    protected $description = 'Anonymise personal data in transactions older than the retention period (RETENTION_TRANSACTIONS_YEARS)';

    public function handle(AnonymiseExpiredData $anonymise): int
    {
        $result = $anonymise->handle();
        $this->info("Anonymised records dated before {$result['before']}: ".array_sum($result['records']));

        return self::SUCCESS;
    }
}
