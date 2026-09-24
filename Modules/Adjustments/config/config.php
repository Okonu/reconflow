<?php

declare(strict_types=1);

return [
    'approval_threshold' => env('ADJUSTMENT_APPROVAL_THRESHOLD', '1000.00'),
    'erp' => [
        'driver' => env('ERP_DRIVER', env('SOURCE_SYSTEMS_DRIVER', 'local')),
    ],
    'accounts' => [
        'receivable' => env('ACCOUNT_RECEIVABLE', '1100-CUSTOMER-RECEIVABLES'),
        'write_off' => env('ACCOUNT_WRITE_OFF', '6150-BAD-DEBT-WRITE-OFF'),
        'refunds_payable' => env('ACCOUNT_REFUNDS_PAYABLE', '2150-CUSTOMER-REFUNDS-PAYABLE'),
        'suspense' => env('ACCOUNT_SUSPENSE', '2190-UNIDENTIFIED-RECEIPTS-SUSPENSE'),
        'revenue' => env('ACCOUNT_REVENUE', '4000-SALES-CASH'),
    ],
];
