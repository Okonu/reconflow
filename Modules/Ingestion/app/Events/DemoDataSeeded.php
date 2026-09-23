<?php

declare(strict_types=1);

namespace Modules\Ingestion\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class DemoDataSeeded
{
    use Dispatchable;

    public function __construct(public readonly array $dates) {}
}
