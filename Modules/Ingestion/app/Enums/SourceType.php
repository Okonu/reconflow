<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

use Modules\Ingestion\Support\Schema\PaymentsSchema;
use Modules\Ingestion\Support\Schema\PostingsSchema;
use Modules\Ingestion\Support\Schema\SalesSchema;
use Modules\Ingestion\Support\Schema\SourceSchema;

enum SourceType: string
{
    case Sales = 'sales';
    case Payments = 'payments';
    case Postings = 'postings';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Payments => 'Payments',
            self::Postings => 'ERP postings',
        };
    }

    public function templateFileName(): string
    {
        return match ($this) {
            self::Sales => 'sales_upload_template.xlsx',
            self::Payments => 'payments_upload_template.xlsx',
            self::Postings => 'erp_postings_upload_template.xlsx',
        };
    }

    public function exportFilePrefix(): string
    {
        return match ($this) {
            self::Sales => 'sales',
            self::Payments => 'payments',
            self::Postings => 'erp_postings',
        };
    }

    public function schema(): SourceSchema
    {
        return match ($this) {
            self::Sales => new SalesSchema,
            self::Payments => new PaymentsSchema,
            self::Postings => new PostingsSchema,
        };
    }

    public function inventoryDataset(): string
    {
        return $this->value;
    }
}
