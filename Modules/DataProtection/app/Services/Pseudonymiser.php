<?php

declare(strict_types=1);

namespace Modules\DataProtection\Services;

use RuntimeException;

final class Pseudonymiser
{
    public function __construct(private readonly string $salt)
    {
        if ($salt === '') {
            throw new RuntimeException('PII_HASH_SALT must be configured');
        }
    }

    public function token(string $value, string $prefix = 'CUST'): string
    {
        $normalised = preg_replace('/\D/', '', $value) ?: $value;

        return $prefix.'_'.substr(hash_hmac('sha256', $normalised, $this->salt), 0, 10);
    }
}
