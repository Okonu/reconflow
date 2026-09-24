<?php

declare(strict_types=1);

namespace Modules\Adjustments\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use App\Support\Money;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Adjustments\Enums\AdjustmentPermission;
use Modules\Adjustments\Enums\AdjustmentState;
use Modules\Adjustments\Models\Adjustment;

final class AdjustmentPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): Response
    {
        return $this->permit($user, AdjustmentPermission::View);
    }

    public function propose(User $user): Response
    {
        return $this->permit($user, AdjustmentPermission::Propose);
    }

    public function approve(User $user, Adjustment $adjustment): Response
    {
        $base = $this->permit($user, AdjustmentPermission::Approve);
        if ($base->denied()) {
            return $base;
        }
        if ($adjustment->proposed_by === $user->getKey()) {
            return Response::deny('You proposed this adjustment, so someone else must approve or reject it (segregation of duties).');
        }
        if ($adjustment->state !== AdjustmentState::PendingApproval) {
            return Response::deny('This adjustment is not awaiting approval.');
        }
        $threshold = Money::of((string) config('adjustments.approval_threshold'));
        if ($adjustment->amount?->isGreaterThan($threshold) && ! $user->can(AdjustmentPermission::ApproveHighValue->value)) {
            return Response::deny('Adjustments above $'.number_format((float) (string) $threshold, 2).' need a Finance Manager (adjustments.approve_high_value).');
        }

        return Response::allow();
    }

    public function reject(User $user, Adjustment $adjustment): Response
    {
        return $this->approve($user, $adjustment);
    }

    public function retry(User $user, Adjustment $adjustment): Response
    {
        return $adjustment->state === AdjustmentState::PostingFailed
            ? $this->permit($user, AdjustmentPermission::Approve)
            : Response::deny('Only failed postings can be retried.');
    }

    public function manageErp(User $user): Response
    {
        return $this->permit($user, AdjustmentPermission::ManageErp);
    }
}
