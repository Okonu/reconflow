<?php

declare(strict_types=1);

namespace Modules\Adjustments\Contracts;

use Modules\Adjustments\DTOs\ErpResponse;

interface ErpClient
{
    public function postJournal(array $request): ErpResponse;
}
