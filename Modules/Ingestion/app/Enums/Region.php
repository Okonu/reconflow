<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum Region: string
{
    case Western = 'Western';
    case Nyanza = 'Nyanza';
    case RiftValley = 'Rift Valley';
    case Central = 'Central';
    case Eastern = 'Eastern';
    case Coast = 'Coast';
}
