<?php

declare(strict_types=1);

namespace Modules\Adjustments\Services;

use App\Support\Money;
use App\Support\Settings\VersionedSettings;
use Brick\Math\BigDecimal;

final class ApprovalSettings
{
    public const SECTION = 'approvals';

    public function __construct(private readonly VersionedSettings $settings) {}

    public function defaults(): array
    {
        return ['approval_threshold' => (string) config('adjustments.approval_threshold')];
    }

    public function all(): array
    {
        return $this->settings->current(self::SECTION, $this->defaults());
    }

    public function threshold(): BigDecimal
    {
        return Money::of((string) $this->all()['approval_threshold']);
    }
}
