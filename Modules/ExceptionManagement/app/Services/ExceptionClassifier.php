<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\ExceptionManagement\Enums\ExceptionCategory;
use Modules\ExceptionManagement\Enums\Severity;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Models\ReconResult;

final class ExceptionClassifier
{
    public function __construct(private readonly WorkflowSettings $settings) {}

    public function category(ReconStatus $status, array $flags = []): ExceptionCategory
    {
        return match ($status) {
            ReconStatus::PendingTiming => ExceptionCategory::Timing,
            ReconStatus::Variance => ($flags['split'] ?? false) ? ExceptionCategory::UnderOverPaymentInstalments : ExceptionCategory::UnderOverPayment,
            ReconStatus::MissingPayment => ExceptionCategory::Reference,
            ReconStatus::DuplicatePayment => ExceptionCategory::DuplicatePayment,
            ReconStatus::MissingPosting, ReconStatus::PostingMismatch, ReconStatus::DuplicatePosting => ExceptionCategory::ErpPosting,
            ReconStatus::UnmatchedPayment => ExceptionCategory::UnknownPayment,
            default => ExceptionCategory::DataQuality,
        };
    }

    public function valueAtRisk(ReconResult $result, ReconStatus $status): BigDecimal
    {
        $value = match ($status) {
            ReconStatus::Variance => $result->variance?->abs(),
            ReconStatus::PostingMismatch => $result->posted_amount !== null && $result->expected_amount !== null ? $result->posted_amount->minus($result->expected_amount)->abs() : null,
            ReconStatus::MissingPayment, ReconStatus::PendingTiming, ReconStatus::MissingPosting, ReconStatus::DuplicatePosting => $result->expected_amount,
            ReconStatus::UnmatchedPayment, ReconStatus::DuplicatePayment => $result->actual_amount,
            default => null,
        };

        return $value ?? Money::zero();
    }

    public function severity(ReconStatus $status, BigDecimal $valueAtRisk): Severity
    {
        if ($status === ReconStatus::PendingTiming) {
            return Severity::Low;
        }
        $bands = $this->settings->bands();
        $severity = match (true) {
            $valueAtRisk->isGreaterThanOrEqualTo((string) $bands['critical_from']) => Severity::Critical,
            $valueAtRisk->isGreaterThanOrEqualTo((string) $bands['high_from']) => Severity::High,
            $valueAtRisk->isGreaterThanOrEqualTo((string) $bands['medium_from']) => Severity::Medium,
            default => Severity::Low,
        };
        $minimumMedium = in_array($status->value, (array) config('exceptionmanagement.minimum_medium_statuses'), true);

        return $minimumMedium && $severity === Severity::Low ? Severity::Medium : $severity;
    }

    public function dueAt(ReconStatus $status, Severity $severity, CarbonImmutable $from): ?CarbonImmutable
    {
        return $status === ReconStatus::PendingTiming ? null : $from->addHours($this->settings->slaHours($severity));
    }
}
