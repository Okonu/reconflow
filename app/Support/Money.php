<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Money
{
    public const SCALE = 2;

    public static function of(BigNumber|int|string $amount): BigDecimal
    {
        if (is_string($amount) && ! preg_match('/^-?\d+(\.\d+)?$/', trim($amount))) {
            throw new InvalidArgumentException("Not a monetary amount: {$amount}");
        }

        return BigDecimal::of(is_string($amount) ? trim($amount) : $amount)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(self::SCALE);
    }

    public static function toString(?BigDecimal $amount): ?string
    {
        return $amount?->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}
