<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Carbon\CarbonImmutable;
use Modules\Ingestion\Enums\PaymentChannel;
use Modules\Ingestion\Enums\SourceType;

final class PaymentsSchema extends SourceSchema
{
    public function source(): SourceType
    {
        return SourceType::Payments;
    }

    public function title(): string
    {
        return 'Payments received (M-Pesa and bank)';
    }

    public function recordKeyColumn(): string
    {
        return 'payment_id';
    }

    public function uniqueKeyColumn(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            new Column('payment_id', Requirement::Required, ColumnType::Text, 'Provider receipt number (M-Pesa receipt or bank reference). Should be unique.', 'SJK3M8Q2XA', width: 18),
            new Column('timestamp', Requirement::Required, ColumnType::DateTime, 'When the payment was received.', '2026-09-22 10:17:42', width: 22),
            new Column('channel', Requirement::Required, ColumnType::Choice, 'MOBILE_MONEY or BANK.', 'MOBILE_MONEY', array_column(PaymentChannel::cases(), 'value'), width: 17),
            new Column('payer_phone', Requirement::Conditional, ColumnType::Phone, '*Required for MOBILE_MONEY. Personal data: masked in the app.', '254700123456', width: 18),
            new Column('amount', Requirement::Required, ColumnType::Money, 'Greater than 0. USD.', '77.00', width: 14),
            new Column('currency', Requirement::Required, ColumnType::Choice, 'USD only.', 'USD', ['USD'], width: 12),
            new Column('reference', Requirement::Optional, ColumnType::Text, 'Account/reference the payer entered; usually the sale transaction_id. May be blank or mistyped.', 'TUP-S-000123', width: 18),
        ];
    }

    protected function rowRules(array $values, array &$errors, ValidationContext $context): void
    {
        if ($values['channel'] === PaymentChannel::MobileMoney->value && $values['payer_phone'] === null) {
            $errors[] = Reason::missing('payer_phone');
        }

        $paidAt = CarbonImmutable::parse((string) $values['timestamp']);
        if ($paidAt->lessThan($context->windowStart()) || $paidAt->greaterThanOrEqualTo($context->windowEnd())) {
            $errors[] = Reason::outsideWindow(
                $paidAt->setTimezone($context->timezone)->format('Y-m-d H:i:s'),
                $context->windowStart()->format('Y-m-d H:i'),
                $context->windowEnd()->format('Y-m-d H:i'),
            );
        }
    }
}
