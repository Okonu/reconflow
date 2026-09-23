<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        return $value === null ? null : Money::of((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value)) {
            throw new InvalidArgumentException("Money attribute [{$key}] must not be a float");
        }
        if (! $value instanceof BigNumber && ! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException("Money attribute [{$key}] has an unsupported type");
        }

        return Money::toString(Money::of($value));
    }
}
