<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum LikelyCause: string
{
    case CustomerUnderpaid = 'customer_underpaid';
    case CustomerOverpaid = 'customer_overpaid';
    case PaymentNotYetReceived = 'payment_not_yet_received';
    case PaymentWithoutReference = 'payment_without_reference';
    case DuplicatePayment = 'duplicate_payment';
    case ErpPostingWrongAmount = 'erp_posting_wrong_amount';
    case ErpPostingMissing = 'erp_posting_missing';
    case DuplicateErpPosting = 'duplicate_erp_posting';
    case UnidentifiedReceipt = 'unidentified_receipt';
    case TimingDifference = 'timing_difference';
    case DataQuality = 'data_quality';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
