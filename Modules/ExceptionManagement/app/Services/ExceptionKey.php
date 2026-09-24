<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\ExceptionManagement\Enums\StatusFamily;
use Modules\Reconciliation\Models\ReconResult;

final class ExceptionKey
{
    public static function identity(ReconResult $result, ?string $businessDate = null): string
    {
        $date = $businessDate ?? $result->business_date->toDateString();
        if ($result->transaction_id !== null && $result->sale_record_id !== null) {
            return "{$date}|sale|{$result->transaction_id}";
        }
        $identities = (array) $result->payment_identities;
        sort($identities);

        return "{$date}|payment|".implode(',', $identities).'|'.($result->transaction_id ?? '');
    }

    public static function key(string $identity, StatusFamily $family): string
    {
        return "{$identity}|{$family->value}";
    }
}
