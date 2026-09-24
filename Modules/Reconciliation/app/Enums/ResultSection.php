<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum ResultSection: string
{
    case Current = 'current';
    case PriorDay = 'prior_day';
}
