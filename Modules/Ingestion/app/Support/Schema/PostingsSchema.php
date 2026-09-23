<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Modules\Ingestion\Enums\PostingStatus;
use Modules\Ingestion\Enums\SourceType;

final class PostingsSchema extends SourceSchema
{
    public function source(): SourceType
    {
        return SourceType::Postings;
    }

    public function title(): string
    {
        return 'ERP / general-ledger postings';
    }

    public function recordKeyColumn(): string
    {
        return 'transaction_id';
    }

    public function uniqueKeyColumn(): string
    {
        return 'journal_id';
    }

    public function columns(): array
    {
        return [
            new Column('journal_id', Requirement::Required, ColumnType::Text, 'Unique ERP journal line ID.', 'JNL-2026-000123', width: 18),
            new Column('posting_date', Requirement::Required, ColumnType::Date, 'Ledger posting date.', '2026-09-22', width: 14),
            new Column('transaction_id', Requirement::Required, ColumnType::Text, 'Sale transaction_id this posting relates to.', 'TUP-S-000123', width: 18),
            new Column('account', Requirement::Required, ColumnType::Text, 'GL account code.', '4000-SALES-CASH', width: 18),
            new Column('amount', Requirement::Required, ColumnType::Money, 'Greater than 0. USD.', '77.00', width: 14),
            new Column('currency', Requirement::Required, ColumnType::Choice, 'USD only.', 'USD', ['USD'], width: 12),
            new Column('status', Requirement::Required, ColumnType::Choice, 'POSTED, PENDING or REVERSED. Only POSTED lines are reconciled.', 'POSTED', array_column(PostingStatus::cases(), 'value'), width: 14),
        ];
    }
}
