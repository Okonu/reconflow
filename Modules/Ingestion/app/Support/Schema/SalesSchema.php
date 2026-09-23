<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Modules\Ingestion\Enums\Region;
use Modules\Ingestion\Enums\SourceType;

final class SalesSchema extends SourceSchema
{
    public function source(): SourceType
    {
        return SourceType::Sales;
    }

    public function title(): string
    {
        return 'Sales (sales / order system)';
    }

    public function recordKeyColumn(): string
    {
        return 'transaction_id';
    }

    public function uniqueKeyColumn(): string
    {
        return 'transaction_id';
    }

    public function columns(): array
    {
        return [
            new Column('transaction_id', Requirement::Required, ColumnType::Text, 'Unique per file. Format TUP-S-NNNNNN.', 'TUP-S-000123', width: 18),
            new Column('business_date', Requirement::Required, ColumnType::Date, 'The trading day being reconciled.', '2026-09-22', width: 14),
            new Column('timestamp', Requirement::Required, ColumnType::DateTime, 'When the sale was recorded.', '2026-09-22 10:15:00', width: 20),
            new Column('agent_id', Requirement::Required, ColumnType::Text, 'Field agent or duka code.', 'AG-014', width: 12),
            new Column('customer_phone', Requirement::Required, ColumnType::Phone, 'Kenyan format 2547XXXXXXXX. Personal data: masked in the app.', '254700123456', width: 16),
            new Column('region', Requirement::Required, ColumnType::Choice, 'One of: '.implode(', ', array_column(Region::cases(), 'value')), 'Western', array_column(Region::cases(), 'value'), width: 14),
            new Column('product_sku', Requirement::Required, ColumnType::Text, 'Product code.', 'FERT-DAP-50KG', width: 22),
            new Column('expected_amount', Requirement::Required, ColumnType::Money, 'Greater than 0. USD.', '77.00', width: 16),
            new Column('currency', Requirement::Required, ColumnType::Choice, 'USD only (single-currency scope).', 'USD', ['USD'], width: 10),
            new Column('payment_reference', Requirement::Optional, ColumnType::Text, 'Reference the customer was asked to pay with; usually the transaction_id.', 'TUP-S-000123', width: 18),
        ];
    }

    protected function rowRules(array $values, array &$errors, ValidationContext $context): void
    {
        if ($values['business_date'] !== $context->dateString()) {
            $errors[] = Reason::businessDateMismatch((string) $values['business_date'], $context->dateString());
        }
    }
}
