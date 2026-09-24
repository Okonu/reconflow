<?php

declare(strict_types=1);

namespace Modules\Notifications\Enums;

enum AlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
