<?php

declare(strict_types=1);

namespace Modules\DataProtection\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

final class MaskPersonalData
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new PersonalDataProcessor);
        }
    }
}
