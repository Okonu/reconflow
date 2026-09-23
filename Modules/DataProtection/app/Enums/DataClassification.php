<?php

declare(strict_types=1);

namespace Modules\DataProtection\Enums;

enum DataClassification: string
{
    case Public = 'Public';
    case Internal = 'Internal';
    case Confidential = 'Confidential';
    case Personal = 'Personal';
}
