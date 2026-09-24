<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Policies;

use App\Support\Authorization\AuthorizesPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Auth\User;
use Modules\Reconciliation\Enums\ReconPermission;
use Modules\Reconciliation\Models\ReconResult;

final class ReconResultPolicy
{
    use AuthorizesPermissions;

    public function view(User $user, ReconResult $result): Response
    {
        return $this->permit($user, ReconPermission::ViewResults);
    }

    public function confirmMatch(User $user, ReconResult $result): Response
    {
        return $this->permit($user, ReconPermission::ConfirmMatches);
    }
}
