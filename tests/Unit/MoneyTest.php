<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;

it('normalises amounts to two decimal places without floats', function (): void {
    expect((string) Money::of('589.25'))->toBe('589.25')
        ->and((string) Money::of(7))->toBe('7.00')
        ->and((string) Money::of('0.005'))->toBe('0.01')
        ->and(Money::toString(Money::zero()))->toBe('0.00');
});

it('rejects values that are not monetary amounts', function (): void {
    Money::of('12,50');
})->throws(InvalidArgumentException::class);

it('refuses to store floats through the money cast', function (): void {
    $model = new class extends Model {};
    $cast = new MoneyCast;

    expect($cast->set($model, 'amount', '10.1', []))->toBe('10.10')
        ->and($cast->get($model, 'amount', '10.10', []))->toBeInstanceOf(BigDecimal::class)
        ->and(fn () => $cast->set($model, 'amount', 10.1, []))->toThrow(InvalidArgumentException::class);
});
