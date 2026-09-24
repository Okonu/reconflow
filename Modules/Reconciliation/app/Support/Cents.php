<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Support;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Cents
{
    public static function fromDecimal(string $amount): int
    {
        return Money::of($amount)->multipliedBy(100)->toInt();
    }

    public static function toDecimal(?int $cents): ?string
    {
        return $cents === null ? null : (string) BigDecimal::ofUnscaledValue($cents, 2);
    }

    public static function percent(int $part, int $whole): ?string
    {
        if ($whole === 0) {
            return null;
        }

        return (string) BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 2, RoundingMode::HalfUp);
    }
}
