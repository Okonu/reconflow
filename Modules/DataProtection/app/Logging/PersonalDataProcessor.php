<?php

declare(strict_types=1);

namespace Modules\DataProtection\Logging;

use Modules\DataProtection\Support\PersonalData;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class PersonalDataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: PersonalData::scrubText($record->message),
            context: PersonalData::scrub($record->context),
            extra: PersonalData::scrub($record->extra),
        );
    }
}
