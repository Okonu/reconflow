<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

enum ExceptionCategory: string
{
    case Timing = 'Timing difference';
    case UnderOverPayment = 'Customer under/over-payment';
    case UnderOverPaymentInstalments = 'Customer under/over-payment (instalments)';
    case Reference = 'Missing/incorrect reference';
    case DuplicatePayment = 'Duplicate payment';
    case ErpPosting = 'ERP posting failure';
    case UnknownPayment = 'Unknown payment';
    case DataQuality = 'Data quality';
}
