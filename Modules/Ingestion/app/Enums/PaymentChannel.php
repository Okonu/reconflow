<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum PaymentChannel: string
{
    case MobileMoney = 'MOBILE_MONEY';
    case Bank = 'BANK';
}
