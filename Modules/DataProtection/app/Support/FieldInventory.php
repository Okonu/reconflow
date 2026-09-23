<?php

declare(strict_types=1);

namespace Modules\DataProtection\Support;

use Modules\DataProtection\Enums\DataClassification as C;

final class FieldInventory
{
    public const FIELDS = [
        'sales' => [
            'transaction_id' => C::Internal,
            'business_date' => C::Internal,
            'timestamp' => C::Internal,
            'agent_id' => C::Confidential,
            'customer_phone' => C::Personal,
            'region' => C::Internal,
            'product_sku' => C::Public,
            'expected_amount' => C::Confidential,
            'currency' => C::Public,
            'payment_reference' => C::Internal,
        ],
        'payments' => [
            'payment_id' => C::Confidential,
            'timestamp' => C::Internal,
            'channel' => C::Internal,
            'payer_phone' => C::Personal,
            'amount' => C::Confidential,
            'currency' => C::Public,
            'reference' => C::Internal,
        ],
        'postings' => [
            'journal_id' => C::Internal,
            'posting_date' => C::Internal,
            'transaction_id' => C::Internal,
            'account' => C::Internal,
            'amount' => C::Confidential,
            'currency' => C::Public,
            'status' => C::Internal,
        ],
        'users' => [
            'name' => C::Personal,
            'email' => C::Personal,
            'password' => C::Confidential,
        ],
    ];

    public static function classify(string $dataset, string $field): ?C
    {
        return self::FIELDS[$dataset][$field] ?? null;
    }

    public static function personalFields(string $dataset): array
    {
        return array_keys(array_filter(self::FIELDS[$dataset] ?? [], fn (C $c): bool => $c === C::Personal));
    }
}
