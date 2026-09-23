<?php

declare(strict_types=1);

namespace Modules\DataProtection\Support;

final class PersonalData
{
    public const MASK = '•';

    private const PHONE = '/(?<!\d)(?:\+?254|0)(?:7|1)\d{8}(?!\d)/';

    private const BEARER = '/bearer\s+[A-Za-z0-9._\-]+/i';

    private const JWT = '/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/';

    private const API_KEY = '/sk-ant-[A-Za-z0-9_\-]+/';

    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'authorization', 'token', 'access_token', 'secret', 'api_key', 'cookie', 'x-xsrf-token'];

    public static function maskPhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $local = match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '254') => '0'.substr($digits, 3),
            strlen($digits) === 10 && str_starts_with($digits, '0') => $digits,
            default => null,
        };
        if ($local === null) {
            return str_repeat(self::MASK, 3).(strlen($digits) > 2 ? substr($digits, -2) : '');
        }

        return substr($local, 0, 2).str_repeat(self::MASK, 2).' '.str_repeat(self::MASK, 3).' '.substr($local, -3);
    }

    public static function maskRecord(string $dataset, array $record): array
    {
        foreach (FieldInventory::personalFields($dataset) as $field) {
            if (array_key_exists($field, $record) && is_string($record[$field])) {
                $record[$field] = self::maskPhone($record[$field]);
            }
        }

        return $record;
    }

    public static function scrubText(string $text): string
    {
        $text = preg_replace(self::API_KEY, '[REDACTED_KEY]', $text) ?? $text;
        $text = preg_replace(self::BEARER, 'Bearer [REDACTED]', $text) ?? $text;
        $text = preg_replace(self::JWT, '[REDACTED_JWT]', $text) ?? $text;

        return preg_replace_callback(self::PHONE, fn (array $m): string => (string) self::maskPhone($m[0]), $text) ?? $text;
    }

    public static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrubText($value);
        }
        if (! is_array($value)) {
            return $value;
        }
        $clean = [];
        foreach ($value as $key => $item) {
            $clean[$key] = is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)
                ? '[REDACTED]'
                : self::scrub($item);
        }

        return $clean;
    }
}
