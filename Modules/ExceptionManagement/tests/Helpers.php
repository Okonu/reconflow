<?php

declare(strict_types=1);

use Modules\ExceptionManagement\Models\ReconException;

function exceptionFor(string $transactionId, string $date = '2026-09-22'): ReconException
{
    return ReconException::query()->whereDate('business_date', $date)->where('transaction_id', $transactionId)->latest('id')->firstOrFail();
}
